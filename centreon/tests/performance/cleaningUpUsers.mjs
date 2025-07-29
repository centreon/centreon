// Required modules
import fetch from 'node-fetch';
import { CENTREON_API_URL, LOGIN, PASSWORD,API_BASE_URL} from './config.mjs';

// Function to log in and retrieve a token
async function login() {
    const loginResponse = await fetch(CENTREON_API_URL+ '/api/latest/login', {
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
        throw new Error('Error during login');
    }

    const loginData = await loginResponse.json();
    const token = loginData?.security?.token;
    if (!token) {
        throw new Error('Authentication token not found');
    }

    return token;
}

// Function to execute an action via the API
async function executeAction(action, token) {
    const response = await fetch(`${API_BASE_URL}?action=action&object=centreon_clapi`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'centreon-auth-token': token
        },
        body: JSON.stringify(action)
    });

    if (!response.ok) {
        throw new Error(`API Error: ${response.statusText}`);
    }

    return response.json();
}

// Function to retrieve items from API
async function getItems(token, objectType) {
    const showAction = {
        action: "SHOW",
        object: objectType
    };

    try {
        const data = await executeAction(showAction, token);
        return data.result || [];
    } catch (error) {
        console.error(`Error while retrieving ${objectType}:`, error);
        return [];
    }
}

// Function to delete an item
async function deleteItem(token, objectType, itemName) {
    const deleteAction = {
        action: "DEL",
        object: objectType,
        values: itemName
    };

    try {
        await executeAction(deleteAction, token);
        console.log(`${objectType} deleted: ${itemName}`);
    } catch (error) {
        console.error(`Error while deleting ${objectType} ${itemName}:`, error);
    }
}

// Main function
async function run() {
    try {
        const token = await login();

        // Delete users
        const users = await getItems(token, "CONTACT");
        const usersToDelete = users.filter(user => user.name.startsWith("user-administrator"));

        for (const user of usersToDelete) {
            await deleteItem(token, "CONTACT", user.name);
        }

        // Delete ACL groups
        const aclGroups = await getItems(token, "ACLGROUP");
        const aclGroupsToDelete = aclGroups.filter(acl => acl.name.startsWith("name-administrator-ACLGROUP"));

        for (const acl of aclGroupsToDelete) {
            await deleteItem(token, "ACLGROUP", acl.name);
        }

        // Delete ACL menus
        const aclMenus = await getItems(token, "ACLMENU");
        const aclMenusToDelete = aclMenus.filter(menu => menu.name.startsWith("name-administrator-ACLMENU"));

        for (const menu of aclMenusToDelete) {
            await deleteItem(token, "ACLMENU", menu.name);
        }

        // Delete contact groups
        const contactGroups = await getItems(token, "CG");
        const contactGroupsToDelete = contactGroups.filter(cg => cg.name.startsWith("contactGroupTestPerf"));

        for (const cg of contactGroupsToDelete) {
            await deleteItem(token, "CG", cg.name);
        }

        console.log("All users, ACL groups, ACL menus, and contact groups have been deleted.");
    } catch (error) {
        console.error("An error occurred while executing the script:", error);
    }
}

// Start execution
run();
