# WABEP License API

Simple PHP 8 license API server for WP AI Blog Engine.

## Endpoints
- `POST /license/check`
- `POST /license/activate`
- `POST /license/deactivate`
- `GET /health`

## Test license keys
These work without creating DB records:
- `TEST-FREE-123` => free
- `TEST-ADVANCED-123` => advanced
- `TEST-PRO-123` => pro

## Deployment
1. Import `sql/schema.sql`
2. Upload all files so that `public/` is the web root, or point the domain document root to `public/`
3. Edit `config/config.php` if needed
4. Verify with `GET /health`

## Notes
- Default domain policy for real licenses is 1 domain per key.
- Test keys bypass DB and are always valid.
- Responses include a simple HMAC signature for tamper checking.
