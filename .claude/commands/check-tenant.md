Run the tenant-guard agent to audit multi-tenant isolation across the entire codebase.

Scan:
- api/app/Http/Controllers/ — for queries missing tenant_id filter
- api/app/Models/ — for models missing BelongsToTenant trait or global scope
- api/routes/api.php — for routes missing tenant middleware
- api/app/Policies/ — for policies not verifying tenant_id

Report all findings grouped by severity: CRITICAL, WARNING, INFO.
End with a summary count and overall pass/fail verdict.
