const axios = require('axios');

/**
 * Script to automate Centreon Roles assignment
 * This mimics the JMeter DSL logic:
 * 1. Login to Web API (ADMIN_TOKEN)
 * 2. Login to Map API (MAP_TOKEN)
 * 3. Fetch ACL ID using Web Token
 * 4. Assign roles using Map Token
 */
async function automateCentreonRoles() {
    try {
        // --- 1. WEB AUTHENTICATION ---
        // Goal: Get ADMIN_TOKEN for configuration tasks
        const webLoginRes = await axios.post('http://172.16.20.138/qa-perf-platform/api/latest/login', {
            security: {
                credentials: {
                    login: "admin",
                    password: "Centreon!2021"
                }
            }
        });
        const adminToken = webLoginRes.data.security.token;
        console.log("✅ Web Authentication successful (ADMIN_TOKEN retrieved).");

        // --- 2. MAP AUTHENTICATION ---
        // Goal: Get MAP_TOKEN for Map-specific API tasks
        const mapLoginRes = await axios.post('http://172.16.21.21:8081/centreon-map/api/beta/auth/sign-in', {
            login: "admin",
            password: "Centreon!2021"
        });
        const mapToken = mapLoginRes.data.jwtToken;
        console.log("✅ Map Authentication successful (MAP_TOKEN retrieved).");

        // --- 3. FETCH ACL GROUP ID ---
        // Using ADMIN_TOKEN to browse configuration
        const aclRes = await axios.get('http://172.16.20.138/qa-perf-platform/api/latest/configuration/access-groups', {
            headers: { 'X-AUTH-TOKEN': adminToken }
        });

        // Filter results to find the ID of 'name-administrator-ACLGROUP'
        const groups = aclRes.data.result;
        const targetGroup = groups.find(g => g.name === 'name-administrator-ACLGROUP');

        if (!targetGroup) {
            throw new Error("❌ Target ACL Group not found!");
        }

        const aclGroupId = targetGroup.id;
        console.log(`✅ Found ACL Group ID: ${aclGroupId}`);

        // --- 4. AFFECT ROLES ---
        // Using MAP_TOKEN (Bearer) to perform the update on Map API
        const rolesPayload = [
            { "name": "ALL", "id": 1, "is_creator": false },
            { "name": "name-administrator-ACLGROUP", "id": aclGroupId, "is_creator": true }
        ];

        await axios.put('http://172.16.21.21:8081/centreon-map/api/beta/aclgroups/roles', rolesPayload, {
            headers: {
                'Authorization': `Bearer ${mapToken}`,
                'Content-Type': 'application/json'
            }
        });

        console.log("🚀 Roles successfully affected to the ACL group!");

    } catch (error) {
        // Detailed error logging
        if (error.response) {
            console.error("🔥 API Error:", error.response.status, error.response.data);
        } else {
            console.error("🔥 Script Error:", error.message);
        }
    }
}

// Run the script
automateCentreonRoles();