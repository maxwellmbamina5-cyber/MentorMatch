# MentorMatch

MentorMatch is being migrated from PHP to Next.js. The Node application is in
`app/` and `lib/`; its current migrated features include the public landing and
account pages, mentor search, and session APIs. The remaining role dashboards,
payments, admin workflows, and scheduled jobs still use the PHP code in
`frontend/` and `backend/`.

The Render Blueprint remains configured for Docker/PHP until those workflows
are migrated. Do not switch the production service to the Node runtime before
feature parity is reached.

To build the Next.js application locally:

```sh
yarn install --frozen-lockfile
yarn build
yarn start
```

The Node app requires `DATABASE_URL` and a `SESSION_SECRET` containing at least
32 characters. See `.env.example` for local configuration.
