# Security policy

## Supported version

Security fixes are applied to the latest commit on `main`.

## Reporting a vulnerability

Please do not open a public issue containing an exploit, credential, database
record, customer document, or other sensitive evidence. Use GitHub's private
security-advisory form for this repository instead:

<https://github.com/awanafnan17/Oggy-Marble-ERP/security/advisories/new>

Include the affected route or component, reproduction steps, impact, and a
minimal proof of concept. Remove real business and personal data before
attaching evidence.

## Deployment responsibility

This public repository contains source code and synthetic configuration only.
Operators are responsible for secret management, HTTPS, database backups,
access control, log retention, patching, and an independent review before a
production rollout. Never commit `.env`, database files, transaction proofs,
backups, exports, or production login details.
