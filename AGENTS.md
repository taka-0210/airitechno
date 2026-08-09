# RISE GATE repository workflow

## Git operations

- After completing and validating a coherent requested change, commit related files and push the current branch to `origin`.
- Stage only files related to the current task. Preserve unrelated user changes.
- If validation, authentication, conflicts, or scope are unclear, report the blocker instead of forcing the operation.

## Deployment operations

- Git push and server deployment are separate operations.
- Deployment workflows must use `workflow_dispatch` only.
- Deployments are run manually from GitHub Actions unless explicitly requested otherwise.
- Preserve server-owned uploads, environment files, caches, inquiries, sessions, and generated content.
- Never commit or display API keys or server credentials.

## Project-specific configuration

- Application: lightweight PHP product catalogue for プロ厨房ヒット新居浜店 / 株式会社アイリテクノ.
- Product source: rise-up.netの厨房君商品API（2026-08-10配置・検証済み）、store ID `265`. 切替期間中はpro-chubo.comの旧APIを停止せず保持する。
- Validation: run PHP syntax checks for every changed PHP file and `git diff --check`.
- Local URL: `http://localhost/airitechno/public/`.
- Demo URL: N/A.
- Production URL: Not configured yet.
- Demo workflow: N/A.
- Production workflow: Not configured yet.
- Protected paths: `.env`, `storage/cache/`, runtime logs, inquiries, sessions, and server-managed files.
