Implement the following feature end-to-end using the feature-orchestrator agent: $ARGUMENTS

The orchestrator must:
1. Design the database schema first and present it for approval
2. After approval, spawn api-agent and web-agent in parallel to implement both sides
3. Run tenant-guard after implementation to verify multi-tenant isolation
4. Report any mismatches between the API contract and what the frontend expects
