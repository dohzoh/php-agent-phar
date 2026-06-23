# 0622 Fix PathSandbox: non-existing intermediate + `..` traversal escape

## 問題

`PathSandbox::resolve()` は、workspace 外への `..` トラバーサルを含むパスのうち、**中間ディレクトリが存在しないパス** を許可してしまいます。

### 原因

`resolve()` の非既存パスブランチ（`file_exists()` が false の場合）では:
1. 祖先ディレクトリ探索が workspace 自身で止まる
2. `str_replace()` による再構成後のパスに `..` が含まれたままになる
3. `realpath()` は中間ディレクトリ非存在により false を返す → symlink チェックをスキップ
4. `isInsideWorkspace()` の prefix 比較 (workspace + DIRECTORY_SEPARATOR で始まる) を未解決の `..` ごと通過してしまう

### 影響

| シナリオ | Sandbox | is_file/stat | 実ファイル操作 |
|----------|---------|-------------|---------------|
| `nonexistent/../../outside/file.txt` 読込 | ALLOWED | FAIL (stat が non-existing 中間で落ちる) | `file_get_contents` で OUTSIDE 読取可能 |
| `nonexistent/../../outside/newfile.txt` 書込 | ALLOWED | FAIL (is_dir が stat で落ちる) | `file_put_contents` で OUTSIDE 書込可能 |

現在 `ReadCapability` / `WriteCapability` 内の `is_file()` / `is_dir()` チェックが二次防御として機能しているが、防御の第一線である Sandbox が通してしまうのは設計上有害。

## 必須修正

### 1. `PathSandbox::resolve()` のパス正規化

`isInsideWorkspace()` に渡す前に、パス文字列中の `..` / `.` セグメントを解決せよ。

- 対象: `src/Filesystem/PathSandbox.php`
- workspace の実在チェックを厳格化: 存在しない workspace を CWD にフォールバックする動作を削除し、例外を投げる
- 新規 private メソッド `normalizePath(string $path): string` を追加:
  - `explode(DIRECTORY_SEPARATOR, $path)` で分解
  - `..` は直前の要素を pop
  - `.` は無視
  - 空要素は無視
- `isInsideWorkspace()` を呼ぶ前に `normalizePath()` を通す（特に再構成後および最終チェック時）

### 2. 回帰テストの追加

- `PathSandboxTest` に以下を追加:
  - 非既存中間 + `..` の拒否 (`nonexistent/../../outside/file.txt`)
  - 非既存中間 + `..` 複数段 (`a/b/c/../../../../outside/file.txt`)
  - 既存中間 + `..` の拒否（sibling 非既存）(`subdir/../sibling/newfile.txt` ← sibling が非既存 → workspace 内に解決されるので許可でOK）
  - workspace 自体が存在しない場合の例外テスト
  - symlink 経由の脱出拒否

### 3. 検証

- `docker compose run --rm test` — 全テスト通過
- `docker compose run --rm test sh -lc "composer validate --no-check-publish"`
- `docker compose run --rm test sh -lc "find . -path ./vendor -prune -o -name '*.php' -print -exec php -l {} \\;"`
- `docker compose run --rm test sh -lc "composer build && php php-agent.phar --help"`
