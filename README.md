# MentorMatch

MentorMatch is being migrated from PHP to Next.js. The Node application is in
`app/` and `lib/`; its current migrated features include the public landing and
account pages, mentor search, and session APIs. The remaining role dashboards,
payments, admin workflows, and scheduled jobs still use the PHP code in
`frontend/` and `backend/`.

The Render Blueprint uses the native Node runtime and Yarn. This is an
incomplete migration: the legacy role dashboards, payments, admin workflows,
and scheduled jobs remain in PHP and are not served by the Node deployment.

To build the Next.js application locally:

```sh
yarn install --frozen-lockfile
yarn build
yarn start
```

The Node app requires `DATABASE_URL` and a `SESSION_SECRET` containing at least
32 characters. Render generates the session secret from the Blueprint. See
`.env.example` for local configuration.

For local development, create `.env.local` with either `DATABASE_URL` or the
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` settings used by
the PHP app. Load `backend/database/schema.sql` into the local PostgreSQL
database before using registration and other database-backed pages.
