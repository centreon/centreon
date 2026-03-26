import { CENTREON_API_URL, LOGIN, PASSWORD, API_BASE_URL } from './config.mjs';

async function login() {
    const loginResponse = await fetch(CENTREON_API_URL + '/api/latest/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            security: {
                credentials: {
                    login: LOGIN,
                    password: PASSWORD
                }
            }
        })
    });

    if (!loginResponse.ok) {
        throw new Error('❌ Error during login');
    }

    const loginData = await loginResponse.json();
    const token = loginData?.security?.token;
    if (!token) {
        throw new Error('❌ Authentication token not found');
    }

    return token;
}

// Function to execute an API action
async function executeAction(action, token) {
    try {
        const response = await fetch(`${API_BASE_URL}?action=action&object=centreon_clapi`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'centreon-auth-token': token
            },
            body: JSON.stringify(action)
        });

        if (!response.ok) {
            throw new Error(`❌ API Error: ${response.statusText}`);
        }

        const data = await response.json();
        console.log('✅ Action successful:', data);
        return data;
    } catch (error) {
        console.error(`❌ Error executing action:\nAction: ${action.action}\nObject: ${action.object}\nValues: ${action.values}\nError: ${error.message}`);
        throw error;
    }
}

(async () => {
    try {
        console.log("🔐 Connecting...");
        const token = await login();

        const action1 = { "action": "reload", "object": "acl" };
        const action2 = { "action": "APPLYCFG", "values": "1" };

        console.log("🚀 Executing API actions...");
        await executeAction(action1, token);
        await executeAction(action2, token);

    } catch (error) {
        console.error("❌ Failed to execute API actions.", error.message);
    }
})();
