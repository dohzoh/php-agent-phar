# 0621 Fix Prompt

Use this prompt for the local implementation agent after the first completion report failed source-code verification.

```text
実装完了報告を受けたが、ソースコード検証の結果、未完了および重大な不具合が見つかっています。
以下を修正し、再度「完了」と言える状態にしてください。

前提:
- まず `docs/todos/0621-plan.md` と `docs/todos/0621-todo.md` を読み直してください。
- `docs/todos/0621/05-task-store.md` と `docs/todos/0621/06-phar-and-docs.md` は未完了です。
- 完了した ToDo のみ `docs/todos/0621-todo.md` を `[x]` にしてください。未実装のままチェックしてはいけません。

必須修正:

1. `PathSandbox` の workspace 脱出バグを修正
- 対象: `src/Filesystem/PathSandbox.php`
- 現状、prefix 比較だけなので、workspace `/tmp/base/ws` に対して `../ws2/secret.txt` が `/tmp/base/ws2/secret.txt` として許可されます。
- workspace 内判定は「完全一致」または「workspace + DIRECTORY_SEPARATOR で始まる」にしてください。
- 例:
  - 許可: `/tmp/base/ws/file.txt`
  - 許可: `/tmp/base/ws/sub/file.txt`
  - 拒否: `/tmp/base/ws2/secret.txt`
  - 拒否: `/tmp/base/ws/../ws2/secret.txt`
- symlink で workspace 外へ出るケースも拒否してください。
- 回帰テストを追加してください。

2. ToDo 05 task store を実装
- 指示書: `docs/todos/0621/05-task-store.md`
- 必須ファイル:
  - `src/Task/TaskRecord.php`
  - `src/Task/TaskStoreInterface.php`
  - `src/Task/FileTaskStore.php`
- `ApiHandler` に `task.status` を追加してください。
- `task.execute` に `"record": true` が指定された場合、結果を file-backed task store に保存して `task_id` を返してください。
- `task.status` は `task_id` から保存済み record を返してください。
- テスト:
  - `FileTaskStoreTest`
  - `ApiHandlerTest` に record/status のケース
  - invalid task id / missing task id / unknown task id

3. ToDo 06 PHAR packaging を修正・検証
- 指示書: `docs/todos/0621/06-phar-and-docs.md`
- 現状 `composer build && php php-agent.phar --help` が `phar.readonly` で失敗しています。
- Docker 内でもビルドできるようにしてください。
- 必要なら `composer.json` の build script を `php -d phar.readonly=0 build.php` に変更してください。
- `build.php` は `vendor/autoload.php` がない場合に明確なエラーを出してください。
- PHAR 内で PSR-4 autoload が正しく動くよう、`src/` と `vendor/` のパスを壊さず格納してください。
- `php php-agent.phar --help` が成功することを確認してください。

4. `CommandRunner` の duration バグを修正
- 対象: `src/CommandRunner.php`
- legacy `run()` の `$duration = microtime(true) - (microtime(true) - 0);` は誤りです。
- `runArgv()` と同様に `$startTime = microtime(true);` を使って正しい duration を返してください。
- テストを追加してください。

5. `CommandRunner::isSafeArgv()` のシグネチャを指示書に合わせる
- 指示書では以下です:
  `public static function isSafeArgv(array $argv, array $allowedBinaries): bool`
- 内部固定の広い `SAFE_COMMANDS` に依存せず、呼び出し側が渡した allow list で判定してください。
- `RunSafeCapability` もこの API を使うように修正してください。
- null byte、空 argv、非 string は拒否してください。
- テストを更新してください。

必須検証:
- `docker compose run --rm test`
- `docker compose run --rm test sh -lc "git config --global --add safe.directory /app && composer validate --no-check-publish"`
- `docker compose run --rm test sh -lc "find . -path ./vendor -prune -o -name '*.php' -print -exec php -l {} \\;"`
- `docker compose run --rm test sh -lc "composer build && php php-agent.phar --help"`

完了条件:
- 全テストが通る
- Composer validate が通る
- PHP lint が通る
- PHAR smoke test が通る
- `docs/todos/0621-todo.md` の 05 と 06 が実装後に `[x]` になっている
- README.md / AGENT.md が最終仕様に更新されている
- 実装差分と検証結果を簡潔に報告すること
```
