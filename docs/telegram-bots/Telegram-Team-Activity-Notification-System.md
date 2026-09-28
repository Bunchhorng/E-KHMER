# Task: Create GitHub → Telegram Team Activity Notification System

I want to create an automatic **GitHub → Telegram notification system** for my project.

The purpose is to notify our Telegram group whenever a team member performs important GitHub activities such as:

* Push code
* Create Pull Request
* Review Pull Request
* Merge Pull Request
* Close Pull Request

The Telegram messages must be clean, simple, and easy for the project leader and team members to understand.

---

# 1. Project Information

Project name:

`ETEC Backend`

GitHub repository:

`[REPOSITORY_URL]`

Main development branch:

`dev`

Production branch:

`main`

Team members work using feature branches:

```text
feature/*
bugfix/*
hotfix/*
```

The `dev` branch is protected.

Only the project leader should be able to directly push/merge into `dev` according to the repository branch protection rules.

---

# 2. Technology

Use:

* GitHub Actions
* GitHub Webhook/Event information through GitHub Actions
* Telegram Bot API
* GitHub Secrets
* YAML

Do NOT create a separate backend server unless it is absolutely necessary.

The notification system should run automatically through GitHub Actions.

---

# 3. GitHub Secrets

The workflow must use GitHub Secrets.

Required secrets:

```text
TELEGRAM_BOT_TOKEN
TELEGRAM_CHAT_ID
```

Never hard-code these values inside the repository.

Never expose the Telegram Bot Token in logs.

Explain clearly how to configure:

```text
GitHub Repository
→ Settings
→ Secrets and variables
→ Actions
→ New repository secret
```

---

# 4. File Structure

Create:

```text
.github/
└── workflows/
    └── telegram-notify.yml
```

Also create documentation:

```text
docs/
└── telegram-github-notification.md
```

The documentation must explain the complete setup and usage.

---

# 5. PUSH Notification

When someone pushes code, send a Telegram message similar to:

```text
🚀 ETEC Backend Update

👤 Developer: Chheangleanghai

🌿 Branch: feature/certificate-ui-update

📝 Commit: update certificate UI

✅ Push completed.
```

Use real GitHub event information dynamically.

Do NOT hard-code:

```text
Chheangleanghai
feature/certificate-ui-update
update certificate UI
```

These are examples only.

The workflow must automatically obtain:

* Developer name
* Branch name
* Commit message

---

# 6. Multiple Commits

If one push contains multiple commits, show the commits clearly.

Example:

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

Avoid creating extremely long Telegram messages.

If there are many commits, show a reasonable number and indicate how many additional commits exist.

---

# 7. Pull Request Created

When a Pull Request is opened, send:

```text
🔵 ETEC Backend Update

👤 Developer: Chheangleanghai

🌿 Branch: feature/certificate-ui-update

🎯 Target: dev

📝 Pull Request: #25

📌 Title: Update certificate UI

🔄 Pull Request opened.
```

The information must come dynamically from GitHub.

---

# 8. Pull Request Review

When a Pull Request is reviewed, send a notification.

Example:

```text
🟡 ETEC Backend Update

👤 Reviewer: Bunchhorng

🌿 Branch: feature/certificate-ui-update

📝 Pull Request: #25

💬 Review: Changes requested

🔎 Review submitted.
```

Support review states such as:

```text
APPROVED
CHANGES_REQUESTED
COMMENTED
```

Display them in a readable format.

---

# 9. Pull Request Merged

When a Pull Request is merged, send:

```text
🟣 ETEC Backend Update

👤 Developer: Bunchhorng

🌿 Branch: feature/certificate-ui-update

🎯 Target: dev

📝 Pull Request: #25

📌 Title: Update certificate UI

✅ Merge completed.
```

Make sure this notification is sent only when the Pull Request is actually merged.

Do not send "merge completed" when a Pull Request is only closed.

---

# 10. Pull Request Closed

If a Pull Request is closed without being merged, send:

```text
🔴 ETEC Backend Update

👤 Developer: Chheangleanghai

🌿 Branch: feature/certificate-ui-update

🎯 Target: dev

📝 Pull Request: #25

❌ Pull Request closed without merge.
```

Do not confuse:

```text
Merged
```

with:

```text
Closed without merge
```

---

# 11. Branch Information

The Telegram notification should clearly show the source branch.

Example:

```text
🌿 Branch: feature/certificate-ui-update
```

For Pull Requests, also show the target branch:

```text
🌿 Branch: feature/certificate-ui-update

🎯 Target: dev
```

---

# 12. Developer Name

Use the GitHub actor/author information dynamically.

Example:

```text
👤 Developer: Chheangleanghai
```

Do not use a hard-coded developer name.

If the GitHub username is available, use the username.

---

# 13. Commit Message

For push events, obtain the commit message from the GitHub event.

Example:

```text
📝 Commit: update certificate UI
```

If multiple commits exist, list them as described above.

Handle special characters safely so Telegram messages do not break.

---

# 14. Telegram Message Formatting

Use clean Telegram formatting.

Preferred style:

```text
🚀 ETEC Backend Update

👤 Developer: {developer}

🌿 Branch: {branch}

📝 Commit: {commit_message}

✅ Push completed.
```

Keep:

* Blank lines between sections
* Short messages
* Easy-to-read formatting
* No raw JSON
* No unnecessary GitHub metadata

---

# 15. Security

The implementation must follow these security rules:

1. Never hard-code `TELEGRAM_BOT_TOKEN`.
2. Never print the bot token.
3. Use GitHub Secrets.
4. Do not expose secrets in Telegram.
5. Do not expose secrets in GitHub Actions logs.
6. Validate environment variables before sending notifications.
7. Handle Telegram API errors safely.

---

# 16. GitHub Actions Events

Configure the workflow to support:

```yaml
on:
  push:
  pull_request:
    types:
      - opened
      - closed
      - reopened
      - synchronize
  pull_request_review:
    types:
      - submitted
```

Only implement events that are necessary.

Avoid sending duplicate notifications.

For example:

A merged Pull Request triggers `pull_request.closed`.

The workflow should detect:

```text
merged == true
```

and send:

```text
🟣 Merge completed.
```

Otherwise send:

```text
🔴 Pull Request closed without merge.
```

---

# 17. Branch Filtering

The system should work with branches such as:

```text
main
dev
feature/*
bugfix/*
hotfix/*
```

Make it easy to modify branch filtering later.

Do not accidentally disable notifications for feature branches.

---

# 18. Error Handling

If Telegram sending fails:

* GitHub Actions should clearly show the error.
* Do not expose the bot token.
* Show a useful error message.
* Do not silently fail.

Example:

```text
❌ Telegram notification failed.
Please check TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID.
```

Do not send this error message to Telegram if Telegram itself is unavailable.

---

# 19. Telegram API

Use the Telegram Bot API.

The basic API endpoint is:

```text
https://api.telegram.org/bot<TOKEN>/sendMessage
```

Use:

```text
chat_id
text
```

and an appropriate parse mode if formatting is used.

Do not hard-code the actual token or chat ID.

---

# 20. Documentation

Create:

```text
docs/telegram-github-notification.md
```

The documentation must include:

## 1. Overview

Explain what the system does.

## 2. Architecture

Show:

```text
Developer
   ↓
GitHub
   ↓
GitHub Actions
   ↓
Telegram Bot API
   ↓
Telegram Group
```

## 3. Create Telegram Bot

Explain how to create a bot using BotFather.

## 4. Get Telegram Chat ID

Explain how to obtain the Telegram group/chat ID.

## 5. Configure GitHub Secrets

Explain:

```text
TELEGRAM_BOT_TOKEN
TELEGRAM_CHAT_ID
```

## 6. GitHub Actions Workflow

Explain the workflow file.

## 7. Supported Events

Document:

```text
Push
Pull Request Opened
Pull Request Review
Pull Request Merged
Pull Request Closed
```

## 8. Telegram Message Examples

Include examples for every supported event.

## 9. Testing

Explain how to test:

```text
Push
Pull Request
Review
Merge
Close
```

## 10. Troubleshooting

Include common problems:

* Telegram bot not receiving messages
* Wrong Chat ID
* Invalid Bot Token
* GitHub Actions failed
* Telegram API error
* Permission problems
* Duplicate notifications

---

# 21. Testing Requirements

After implementation, verify:

### Test 1 — Push

```text
feature/test
```

Push a commit.

Expected Telegram:

```text
🚀 ETEC Backend Update

👤 Developer: ...

🌿 Branch: feature/test

📝 Commit: ...

✅ Push completed.
```

### Test 2 — Pull Request

Create:

```text
feature/test → dev
```

Expected Telegram notification.

### Test 3 — Review

Submit a Pull Request review.

Expected Telegram notification.

### Test 4 — Merge

Merge the Pull Request.

Expected:

```text
🟣 ETEC Backend Update

...

✅ Merge completed.
```

### Test 5 — Close without merge

Close a Pull Request without merging.

Expected:

```text
🔴 ETEC Backend Update

...

❌ Pull Request closed without merge.
```

---

# 22. Important Implementation Requirements

Before finishing:

1. Check whether `.github/workflows/` already exists.
2. Do not overwrite existing workflows unnecessarily.
3. Check whether Telegram notification functionality already exists.
4. If it exists, improve it instead of creating duplicate functionality.
5. Keep the implementation simple.
6. Use clean YAML.
7. Add comments explaining important sections.
8. Do not expose secrets.
9. Avoid duplicate Telegram notifications.
10. Test the workflow syntax.
11. Verify all supported GitHub events.
12. Update the Markdown documentation.

---

# 23. Final Deliverables

I expect these files:

```text
.github/
└── workflows/
    └── telegram-notify.yml

docs/
└── telegram-github-notification.md
```

The final system must automatically send clean Telegram activity logs like:

```text
🚀 ETEC Backend Update

👤 Developer: Chheangleanghai

🌿 Branch: feature/certificate-ui-update

📝 Commit: update certificate UI

✅ Push completed.
```

Do not give me only an explanation.

Actually create/update the required files and provide the complete implementation.

Before making changes, inspect the existing project structure and existing GitHub workflows to avoid breaking anything.
