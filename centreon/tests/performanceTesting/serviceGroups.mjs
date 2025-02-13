import { connectToDatabase } from './dbConfig.mjs';
import dotenv from 'dotenv';

dotenv.config();

const PROPERTY_NAME = 'servicegroup';

// 🔹 Generate a random integer between min and max
function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

// 🔹 Secure SQL query execution
async function executeQuery(connection, query, params = []) {
    try {
        const [rows] = await connection.execute(query, params);
        return rows;
    } catch (error) {
        console.error("❌ SQL Error:", error.message);
        throw error;
    }
}

// 🔹 Inject service groups
async function injectServiceGroups(connection, serviceGroup, properties) {
    const ids = [];

    const count = parseInt(process.env.NUMBER_OF_SERVICE_GROUPS, 10) || 10;

    // Get the max existing ID to start inserting from
    const result = await executeQuery(connection, 'SELECT MAX(sg_id) AS max FROM servicegroup');
    const firstId = (parseInt(result[0]?.max, 10) || 0) + 1;
    const maxId = firstId + count;

    const baseQuery = 'INSERT INTO servicegroup (sg_id, sg_name, sg_alias) VALUES ';
    let valuesQuery = '';
    let insertCount = 0;

    const name = `${serviceGroup.name}_`;
    const alias = `${serviceGroup.alias}_`;

    for (let i = firstId; i < maxId; i++) {
        ids.push(i);
        insertCount++;

        valuesQuery += `(${i}, "${name}${i}", "${alias}${i}"),`;

        if (insertCount === 50000) {
            await executeQuery(connection, baseQuery + valuesQuery.slice(0, -1));
            insertCount = 0;
            valuesQuery = '';
        }
    }

    if (valuesQuery.length > 0) {
        await executeQuery(connection, baseQuery + valuesQuery.slice(0, -1));
    }

    console.log(`✅ Inserted ${ids.length} service groups.`);

    return ids;
}

// 🔹 Inject relations between service groups and services starting with 'TestService_'
async function injectServiceGroupRelations(connection, serviceGroupIds) {
    // Get all services where service_description starts with "TestService_"
    const services = await executeQuery(connection, "SELECT service_id FROM service WHERE service_description LIKE 'TestService_%'");

    if (services.length === 0) {
        console.log("❌ No services found with 'TestService_' prefix.");
        return;
    }

    console.log(`✅ Found ${services.length} services starting with 'TestService_'.`);

    const relationBaseQuery = 'INSERT INTO servicegroup_relation (servicegroup_sg_id, service_service_id) VALUES ';
    let relationValuesQuery = '';
    let relationInsertCount = 0;

    for (const service of services) {
        // Select a random service group ID
        const randomServiceGroupId = serviceGroupIds[Math.floor(Math.random() * serviceGroupIds.length)];

        relationInsertCount++;
        relationValuesQuery += `(${randomServiceGroupId}, ${service.service_id}),`;

        if (relationInsertCount === 50000) {
            await executeQuery(connection, relationBaseQuery + relationValuesQuery.slice(0, -1));
            relationInsertCount = 0;
            relationValuesQuery = '';
        }
    }

    if (relationValuesQuery.length > 0) {
        await executeQuery(connection, relationBaseQuery + relationValuesQuery.slice(0, -1));
    }

    console.log(`✅ Inserted service group relations for services starting with 'TestService_'.`);
}

// 🔹 Purge service groups
async function purgeServiceGroups(connection) {
    await executeQuery(connection, 'TRUNCATE TABLE servicegroup');
    console.log("✅ Service groups table truncated.");
}

// 🔹 Main function
async function main() {
    const connection = await connectToDatabase();

    const serviceGroup = {
        name: 'MyServiceGroup',
        alias: 'MyServiceGroupAlias'
    };

    try {
        // Inject service groups
        const serviceGroupIds = await injectServiceGroups(connection, serviceGroup, {});

        if (serviceGroupIds.length === 0) {
            console.log("❌ No service groups created.");
            return;
        }

        // Inject relations with 'TestService_' services
        await injectServiceGroupRelations(connection, serviceGroupIds);

    } catch (error) {
        console.error("❌ Error during service group injection:", error);
    } finally {
        await connection.end();
        console.log("🔌 Connection closed.");
    }
}

// Execute the script
main();
