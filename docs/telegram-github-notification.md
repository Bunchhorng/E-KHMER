# GitHub → Telegram Team Activity Notification System

Automatic notifications from GitHub to your Telegram group whenever a team member:

- Pushes code
- Opens a Pull Request
- Reviews a Pull Request
- Merges a Pull Request
- Closes a Pull Request (without merging)

Everything runs through **GitHub Actions** — no extra backend server is needed.

---

## 1. Overview

A GitHub Actions workflow listens for `push`, `pull_request`, and `pull_request_review` events. For each activity it builds a short, clean message and sends it to your Telegram group using the Telegram Bot API.

Messages are plain text (no Markdown/HTML parse mode), so commit messages and PR titles containing special characters can never break the message.

**Project info**

| Field | Value |
| --- | --- |
| Project name | `ETEC Backend` |
| Repository | `https://github.com/Bunchhorng/ecommerce_website_dashboard.git` |
| Main development branch | `dev` (protected) |
| Production branch | `main` |
| Feature branches | `feature/*`, `bugfix/*`, `hotfix/*`, `fix/*` |

---

## 2. Architecture

```text
Developer
   ↓
GitHub
   ↓
GitHub Actions  ( .github/workflows/telegram-notify.yml )
   ↓
Telegram Bot API  ( https://api.telegram.org/bot<TOKEN>/sendMessage )
   ↓
Telegram Group
```

---

## 3. Create Telegram Bot

1. Open Telegram and search for **@BotFather** (the official bot creator).
2. Send the command: `/newbot`
3. Choose a name, e.g. `ETEC Notifier`.
4. Choose a username ending in `bot`, e.g. `etec_notifier_bot`.
5. BotFather replies with an **HTTP API token**, for example:

   ```text
   123456789:AAE...your_unique_token...AABB
   ```

6. Save this token. It is your `TELEGRAM_BOT_TOKEN`.

---

## 4. Get Telegram Chat ID

1. Add the bot you created to your team's Telegram group (Group → Settings → Members → Add Member → select the bot).
2. Get the chat ID using one of these methods:

   **Method A — getUpdates**
   ```bash
   curl "https://api.telegram.org/bot<YOUR_TOKEN>/getUpdates"
   ```
   Make any post in the group, re-run the command, and read the value at `result[0].message.chat.id`. Group IDs are negative numbers, e.g. `-1001234567890`.

   **Method B — a lookup bot**
   - Add **@userinfobot** or **@getidsbot** to the group and send it a message — it replies with the chat ID.

3. Save this ID. It is your `TELEGRAM_CHAT_ID`.

> Placeholder: replace `<YOUR_TOKEN>` with the real token. On Windows use `curl.exe` in PowerShell.

---

## 5. Configure GitHub Secrets

The workflow reads two secrets. They are never written in the repository and never logged.

| Secret | Value |
| --- | --- |
| `TELEGRAM_BOT_TOKEN` | The bot token from BotFather |
| `TELEGRAM_CHAT_ID` | Your Telegram group chat ID |

Add them here:

```text
GitHub Repository
  → Settings
    → Secrets and variables
      → Actions
        → New repository secret
```

1. Click **New repository secret**.
2. **Name:** `TELEGRAM_BOT_TOKEN`, **Secret:** your token → **Add secret**.
3. Repeat for `TELEGRAM_CHAT_ID`.

When a secret is required but missing, the workflow fails with a clear error and **does not** send anything:

```text
❌ TELEGRAM_BOT_TOKEN secret is not configured.
```

---

## 6. GitHub Actions Workflow

Files created:

```text
.github/workflows/telegram-notify.yml
.github/actions/telegram-notify/action.yml
```

**`.github/workflows/telegram-notify.yml`** — the trigger and the message builders:

- One job per event type (`push`, PR opened, PR review, PR merged, PR closed).
- Each job builds the message text from the live GitHub event payload (`$GITHUB_EVENT_PATH`), never from hard-coded values.
- A merged PR fires `pull_request.closed`; the job checks `pull_request.merged == true` to send **Merge completed**, otherwise it sends **Closed without merge**.
- The `synchronize` event is intentionally not handled (a push to a PR branch already triggers the `push` notification — handling both would send duplicates).

**`.github/actions/telegram-notify/action.yml`** — the shared send step reused by every job:

1. Validates that `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID` are set.
2. Sends the message file via `curl --data-urlencode "text@<file>"` (special characters are safely encoded).
3. Fails loudly on error while never printing the token.

**Branch coverage** — the push trigger has **no branch filter**, so pushes to *every* branch (feature, bugfix, hotfix, dev, main, experimental, ...) are reported:

```yaml
on:
  push:
```

Pull Request and review notifications are also not branch-filtered, so PRs from any branch are always reported. To restrict notifications to specific branches later, add a `branches:` list under `on.push` (e.g. `branches: [main, dev, "feature/**"]`).

---

## 7. Supported Events

| GitHub event | Action | Telegram notification |
| --- | --- | --- |
| `push` | — | 🚀 Push with commit list |
| `pull_request` | `opened` / `reopened` | 🔵 Pull Request opened |
| `pull_request_review` | `submitted` (`APPROVED` / `CHANGES_REQUESTED` / `COMMENTED`) | 🟡 Review submitted |
| `pull_request` | `closed` + `merged == true` | 🟣 Merge completed |
| `pull_request` | `closed` + `merged != true` | 🔴 Closed without merge |

---

## 8. Telegram Message Examples

### Push (single commit)

```text
🚀 ETEC Backend Update

👤 Developer: Chheangleanghai

🌿 Branch: feature/certificate-ui-update

📝 Commit: update certificate UI

✅ Push completed.
```

### Push (multiple commits — up to 5 shown)

```text
🚀 ETEC Backend Update

👤 Developer: Chheangleanghai

🌿 Branch: feature/certificate-ui-update

📝 Commits:
• update certificate UI
• fix certificate modal
• fix certificate validation

📦 Total commits: 3

✅ Push completed.
```

### Pull Request opened

```text
🔵 ETEC Backend Update

👤 Developer: Chheangleanghai

🌿 Branch: feature/certificate-ui-update

🎯 Target: dev

📝 Pull Request: #25

📌 Title: Update certificate UI

🔄 Pull Request opened.
```

### Pull Request review

```text
🟡 ETEC Backend Update

👤 Reviewer: Bunchhorng

🌿 Branch: feature/certificate-ui-update

📝 Pull Request: #25

💬 Review: Changes requested

🔎 Review submitted.
```

Review states are shown readably: `APPROVED` → "Approved", `CHANGES_REQUESTED` → "Changes requested", `COMMENTED` → "Comment only".

### Pull Request merged

```text
🟣 ETEC Backend Update

👤 Developer: Bunchhorng

🌿 Branch: feature/certificate-ui-update

🎯 Target: dev

📝 Pull Request: #25

📌 Title: Update certificate UI

✅ Merge completed.
```

### Pull Request closed without merge

```text
🔴 ETEC Backend Update

👤 Developer: Chheangleanghai

🌿 Branch: feature/certificate-ui-update

🎯 Target: dev

📝 Pull Request: #25

📌 Title: Update certificate UI

❌ Pull Request closed without merge.
```

---

## 9. Testing

Create a scratch branch `feature/test` and walk through each scenario.

### Test 1 — Push

```bash
git checkout -b feature/test
echo "test" >> README.md
git add README.md
git commit -m "test telegram notification"
git push -u origin feature/test
```

Expected: 🚀 Push notification with branch `feature/test`.

### Test 2 — Pull Request opened

```text
feature/test → dev
```

Open a Pull Request on GitHub targeting `dev`.
Expected: 🔵 Pull Request opened notification.

### Test 3 — Review

On the PR page → **Files changed** → **Review changes** → choose **Approve**, **Request changes**, and **Comment** (one submission each).
Expected: 🟡 Review notification for each state.

### Test 4 — Merge

Click **Merge pull request**.
Expected:

```text
🟣 ETEC Backend Update
...
✅ Merge completed.
```

### Test 5 — Close without merge

Open a second PR, then click **Close pull request** (do not merge).
Expected:

```text
🔴 ETEC Backend Update
...
❌ Pull Request closed without merge.
```

Finally delete `feature/test`.

---

## 10. Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Bot never receives messages | Secrets not configured | Add `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID` (section 5). |
| "Secret is not configured" error | Missing / misnamed secret | Secret names are case-sensitive — check spelling. |
| Wrong Chat ID | Copied the bot's own ID instead of the group's | Group IDs are negative; verify with `getUpdates` (section 4). |
| Invalid Bot Token | Token from another bot / truncated | Ask BotFather: `/token` on your bot to see the correct token. |
| GitHub Actions failed | See workflow logs | Open the failing run → step output. Token values are masked. |
| HTTP 400 from Telegram | Bot not a member of the group, or empty chat ID | Add the bot to the group and verify the chat ID. |
| Bot can't be added to group | Group privacy settings / no permission | Promote bot to admin or adjust group privacy settings. |
| Duplicate notifications | A second workflow also handles the same events | Only one workflow should list these `on:` events. |
| Permission problems | `pull_request_review` events need the bot to complete a review | Notifications need no repo write access — only the GitHub **token** to authenticate is set. Event delivery works automatically. |

### Security rules enforced

1. Secrets come from GitHub Secrets only — never hard-coded.
2. The bot token is only available as an environment variable in the send step.
3. Logs never print the token (values are masked by GitHub).
4. Secrets are validated before any request is sent.
5. If Telegram itself is unreachable, the error is reported in the Actions log — not to Telegram.
6. The action fails the workflow run so failures are never silent.