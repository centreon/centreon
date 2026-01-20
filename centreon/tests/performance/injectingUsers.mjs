// Required modules
import fetch from 'node-fetch';
import fs from 'fs';
import { CENTREON_API_URL, LOGIN, PASSWORD, API_BASE_URL, NUMBER_OF_USERS } from './config.mjs';

// Base actions to create users
const baseActions = [
    {
        action: "ADD",
        object: "CONTACT",
        values: "user-administrator;user-administrator;user-administrator@centreon.test;Centreon@2023;0;1;en_US;local"
    },
    {
        action: "SETPARAM",
        object: "CONTACT",
        values: "user-administrator;reach_api;1"
    }
];

// Global actions to be executed once
const globalActions = [
    {
        action: "ADD",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;alias-administrator-ACLMENU"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;0;Home;Custom Views"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;0;Home;Dashboards;Administrator"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;1;Monitoring;Event Logs"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;1;Monitoring;Status Details"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;4;Administration"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;1;Monitoring;Performances"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;1;Monitoring;Downtimes"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;2;Reporting;Availability;Hosts"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;2;Reporting;Availability;Services"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;2;Reporting;Availability;Host Groups"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;2;Reporting;Availability;Service Groups"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;3;Configuration;Hosts"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;3;Configuration;Services"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;3;Configuration;Notifications"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;3;Configuration;SNMP Traps"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;3;Configuration;Knowledge Base"
    },
    {
        action: "GRANTRW",
        object: "ACLMENU",
        values: "name-administrator-ACLMENU;3;Configuration;Pollers;Export Configuration"
    },
    {
        action: "ADD",
        object: "ACLGROUP",
        values: "name-administrator-ACLGROUP;alias-administrator-ACLGROUP"
    },
    {
        action: "ADDMENU",
        object: "ACLGROUP",
        values: "name-administrator-ACLGROUP;name-administrator-ACLMENU"
    },
    {
        action: "ADD",
        object: "CG",
        values: "contactGroupTestPerf;contactGroupTestPerf"
    },
    {
        action: "SETCONTACTGROUP",
        object: "ACLGROUP",
        values: "name-administrator-ACLGROUP;contactGroupTestPerf"
    }
];

// Array to store created users
const usersData = [];

// Function to log in and retrieve a token
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

// Function to execute an action via the API
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
        console.error(`❌ Error executing action:
            Action: ${action.action}
            Object: ${action.object}
            Values: ${action.values}
            Error: ${error.message}`);
        throw error;  // Relaunch the error after logging
    }
}

// Write users to a .CSV file
function writeUsersToCSV(users) {
    const csvContent = users.map(user => `${user.username},${user.password}`).join('\n');
    const header = 'Username,Password\n';

    fs.writeFileSync('users.csv', header + csvContent, 'utf8');
    console.log('✅ File users.csv created successfully.');
}

// Generate actions for multiple users
function generateActionsForUsers(baseActions, userCount) {
    const allActions = [];
    for (let i = 1; i <= userCount; i++) {
        const username = `user-administrator-${i}`;
        const userActions = baseActions.map(action => {
            const newAction = { ...action };
            newAction.values = action.values.replace(/user-administrator/g, username);
            return newAction;
        });
        allActions.push({ username, password: 'Centreon@2023', actions: userActions });
    }
    return allActions;
}

// Generate the SETCONTACT command for all users
async function executeSetContactForAllUsers(token, users) {
    // Concatenate all usernames with `|`
    const userNames = users.map(user => user.username).join('|');

    // Build the SETCONTACT action
    const setContactAction = {
        action: "SETCONTACT",
        object: "ACLGROUP",
        values: `name-administrator-ACLGROUP;${userNames};`
    };

    try {
        // Execute the action
        await executeAction(setContactAction, token);
        console.log('✅ SETCONTACT action executed for all users:', userNames);
    } catch (error) {
        console.error('❌ Error executing SETCONTACT action for all users:', error);
    }
}

// Execute global actions once
async function executeGlobalActions(token) {
    for (const action of globalActions) {
        try {
            await executeAction(action, token);
        } catch (error) {
            console.error(`❌ Error executing global action:
                Failed action: ${error.message}`);
        }
    }
}

// Execute all actions for multiple users
async function executeAllActions(token, userCount) {
    const allUsers = generateActionsForUsers(baseActions, userCount);

    for (const user of allUsers) {
        try {
            for (const action of user.actions) {
                await executeAction(action, token);
            }
            usersData.push({ username: user.username, password: user.password });
        } catch (error) {
            console.error(`❌ Error executing actions for user ${user.username}:
                Failed action: ${error.message}`);
        }
    }

    // Write users to the CSV file
    writeUsersToCSV(usersData);

    // Execute the SETCONTACT action for all users
    await executeSetContactForAllUsers(token, usersData);
}

// Main function
async function run() {
    try {
        const token = await login();
        const userCount = NUMBER_OF_USERS;
        await executeGlobalActions(token);
        await executeAllActions(token, userCount);
    } catch (error) {
        console.error('❌ Critical error:', error.message);
    }
}

// Run the script
run();