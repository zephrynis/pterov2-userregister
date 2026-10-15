# Pinned SDK baseline

SDK **2.0.0-beta.6**, copied on 2026-10-15 from `/app/packages/sdk` in the local Panel container, revision [`ea742d332f5861a81e502857446b8df9cb9f0254`](https://github.com/pterodactyl/panel/tree/ea742d332f5861a81e502857446b8df9cb9f0254/packages/sdk), release **v2.0.0-beta.2**. `vendor/sdk` is development-only; runtime archives exclude it and use the Panel import map. Preserve upstream MIT licensing and the lockfile. The image has no generated SDK testing runtime; frontend tests mock the SDK.

The [v2 docs](https://docs.pterodactyl.io/v2/extensions/reference) MCP snapshot `723ee9fb` synced 2026-10-14 still shows SDK beta.4 and component replacements. Installed beta.6 types, schema, preset, and PHP source take precedence. The Panel compatibility API still reports `2.0.0-dev`; manifests accept that value but exclude Panel beta.3 and SDK beta.7 until retested.
