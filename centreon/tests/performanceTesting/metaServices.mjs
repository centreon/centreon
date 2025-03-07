import { randomInt } from 'crypto';
import dotenv from 'dotenv';
import { connectToDatabase } from './dbConfig.mjs';

// Load environment variables
dotenv.config();

const NUMBER_OF_METASERVICES = parseInt(process.env.NUMBER_OF_METASERVICES, 10);
if (isNaN(NUMBER_OF_METASERVICES) || NUMBER_OF_METASERVICES <= 0) {
    console.error("❌ ERROR: The NUMBER_OF_METASERVICES variable must be a positive integer.");
    process.exit(1);
}

// Secure SQL query execution
async function executeQuery(connection, query, params = []) {
    try {
        const [rows] = await connection.execute(query, params);
        return rows;
    } catch (error) {
        console.error("❌ SQL Error:", error.message);
        throw error;
    }
}

// Ensure or create the "_Module_Meta" host
async function ensureHostMeta(connection) {
    const rows = await executeQuery(connection, 'SELECT host_id FROM host WHERE host_name = "_Module_Meta"');
    let hostId = rows.length > 0 ? rows[0].host_id : null;

    if (!hostId) {
        await executeQuery(connection, 'INSERT INTO host (host_name, host_register, host_activate) VALUES ("_Module_Meta", "2", "1")');
        const newRows = await executeQuery(connection, 'SELECT host_id FROM host WHERE host_name = "_Module_Meta"');
        hostId = newRows[0].host_id;
        console.log(`✅ "_Module_Meta" host created with ID: ${hostId}`);
    }

    return hostId;
}

// Retrieve valid IDs for `check_period`
async function getTimePeriodIds(connection) {
    const rows = await executeQuery(connection, 'SELECT tp_id FROM timeperiod');
    return rows.map(row => row.tp_id);
}

// Retrieve the service ID for a meta_id
async function getServiceIdFromMetaId(connection, meta_id, meta_name) {
    const composed_name = 'meta_' + meta_id;
    const query = `
        SELECT service_id
        FROM service
        WHERE service_register = '2'
        AND service_description = ?
        AND display_name = ?
    `;

    const [rows] = await connection.execute(query, [composed_name, meta_name]);

    if (rows.length > 0) {
        return rows[0].service_id;
    } else {
        console.log(`❌ Service for Meta ${meta_id} not found. Creating a new service.`);
        return null;  // Return null to indicate a new service should be created
    }
}

// Create a service for a metaservice
async function createServiceForMeta(connection, meta_id) {
    const serviceDescription = `meta_${meta_id}`;
    const displayName = `service_${meta_id}`;
    const query = `
        INSERT INTO service (service_description, display_name, service_register, service_activate)
        VALUES (?, ?, '2', '1')
    `;
    const [result] = await connection.execute(query, [serviceDescription, displayName]);
    console.log(`✅ Service for Meta ${meta_id} created with ID: ${result.insertId}`);
    return result.insertId;
}

// Inject metaservices
async function injectMetaservice(connection, count, timeperiodIds) {
    const ids = [];
    const rows = await executeQuery(connection, 'SELECT MAX(meta_id) AS max FROM meta_service');
    let firstId = (rows[0].max || 0) + 1;
    const maxId = firstId + count;
    const baseQuery = `INSERT INTO meta_service (
        meta_id, meta_name, meta_display, metric, calcul_type, data_source_type, meta_select_mode,
        check_period, max_check_attempts, normal_check_interval, retry_check_interval, notifications_enabled,
        warning, critical, meta_activate
    ) VALUES `;

    let valuesQuery = [];
    let insertCount = 0;

    for (let i = firstId; i < maxId; i++) {
        ids.push(i);
        insertCount++;

        const criticalThreshold = randomInt(1, 100000000000);
        const warningThreshold = Math.floor(0.8 * criticalThreshold);
        const timeperiod = timeperiodIds.length > 0 ? timeperiodIds[Math.floor(Math.random() * timeperiodIds.length)] : 1;

        valuesQuery.push(`(${i}, "Meta_${i}", "calculated value : %d", "metric.1", "AVE", 0, "2", ${timeperiod}, 3, 5, 1, "1", ${warningThreshold}, ${criticalThreshold}, "1")`);

        if (insertCount === 50000) {
            await executeQuery(connection, baseQuery + valuesQuery.join(', '));
            valuesQuery = [];
            insertCount = 0;
        }
    }

    if (valuesQuery.length > 0) {
        await executeQuery(connection, baseQuery + valuesQuery.join(', '));
    }

    console.log(`✅ ${count} meta_services inserted.`);
    return ids;
}

// Inject services
async function injectService(connection, metaserviceIds) {
    const serviceIds = [];  // Array to store inserted IDs
    for (let metaId of metaserviceIds) {
        let serviceId = await getServiceIdFromMetaId(connection, metaId, `Meta_${metaId}`);

        // If the service does not exist, create it
        if (!serviceId) {
            serviceId = await createServiceForMeta(connection, metaId);
        }

        serviceIds.push(serviceId);
    }

    console.log(`✅ Associated services created with IDs: ${serviceIds.join(', ')}`);
    return serviceIds;
}

// Link services to the host
async function linkHostServiceRelation(connection, hostId, serviceIds) {
    const baseQuery = 'INSERT INTO host_service_relation (host_host_id, service_service_id) VALUES ';
    let valuesQuery = [];
    let insertCount = 0;

    for (let serviceId of serviceIds) {
        valuesQuery.push(`(${hostId}, ${serviceId})`);
        insertCount++;

        if (insertCount === 50000) {
            await executeQuery(connection, baseQuery + valuesQuery.join(', '));
            valuesQuery = [];
            insertCount = 0;
        }
    }

    if (valuesQuery.length > 0) {
        await executeQuery(connection, baseQuery + valuesQuery.join(', '));
    }

    console.log(`✅ Host-service relations created.`);
}

// Main execution
async function main() {
    const connection = await connectToDatabase();

    try {
        console.log("🚀 Starting meta services injection");
        const timeperiodIds = await getTimePeriodIds(connection);
        const metaserviceIds = await injectMetaservice(connection, NUMBER_OF_METASERVICES, timeperiodIds);
        const serviceIds = await injectService(connection, metaserviceIds);
        const hostId = await ensureHostMeta(connection);
        await linkHostServiceRelation(connection, hostId, serviceIds);
    } catch (error) {
        console.error("❌ Error:", error);
    } finally {
        await connection.end();
        console.log("🔻 Connection closed.");
    }
}

main();
