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

// Function to execute an SQL query safely
async function executeQuery(connection, query, params = []) {
    try {
        const [rows] = await connection.execute(query, params);
        return rows;
    } catch (error) {
        console.error("❌ SQL Error:", error.message);
        throw error;
    }
}

// Verify or create the "_Module_Meta" host
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

// Get valid time period IDs
async function getTimePeriodIds(connection) {
    const rows = await executeQuery(connection, 'SELECT tp_id FROM timeperiod');
    return rows.map(row => row.tp_id);
}

// Verify or create a service for a given meta_id
async function getOrCreateServiceForMeta(connection, meta_id) {
    const serviceDescription = `meta_${meta_id}`;
    const displayName = `Meta_${meta_id}`;

    console.log(`🔍 Checking service: ${serviceDescription} - ${displayName}`);

    const query = `
        SELECT service_id FROM service
        WHERE service_register = '2'
        AND service_description = ?
        AND (display_name = ? OR display_name IS NULL OR display_name = '')
        LIMIT 1
    `;

    const rows = await executeQuery(connection, query, [serviceDescription, displayName]);

    if (rows.length > 0) {
        console.log(`✅ Found existing service ID: ${rows[0].service_id}`);
        return rows[0].service_id;
    }

    console.log(`❌ Service not found, creating a new one.`);
    return createServiceForMeta(connection, meta_id);
}

// Create a service for a given meta_id
async function createServiceForMeta(connection, meta_id) {
    const serviceDescription = `meta_${meta_id}`;
    const displayName = `Meta_${meta_id}`;

    console.log(`🆕 Creating service: ${serviceDescription} - ${displayName}`);

    const query = `
        INSERT INTO service (service_description, display_name, service_register, service_activate)
        VALUES (?, ?, '2', '1')
    `;

   const result = await executeQuery(connection, query, [serviceDescription, displayName]);

    if (result && result.insertId) {
        console.log(`✅ Service created with ID: ${result.insertId}`);
        return result.insertId;
    } else {
        throw new Error(`❌ ERROR: Service creation failed for Meta ${meta_id}`);
    }
}

// Insert meta_services
async function injectMetaservice(connection, count, timeperiodIds) {
    const ids = [];
    const rows = await executeQuery(connection, 'SELECT MAX(meta_id) AS max FROM meta_service');
    let firstId = (rows[0].max || 0) + 1;
    const maxId = firstId + count;

    console.log(`🚀 Inserting ${count} meta_services...`);

    for (let i = firstId; i < maxId; i++) {
        ids.push(i);

        const criticalThreshold = randomInt(1, 100000);
        const warningThreshold = Math.floor(0.8 * criticalThreshold);
        const timeperiod = timeperiodIds.length > 0 ? timeperiodIds[Math.floor(Math.random() * timeperiodIds.length)] : 1;

        const query = `INSERT INTO meta_service (
            meta_id, meta_name, meta_display, metric, calcul_type, data_source_type, meta_select_mode,
            check_period, max_check_attempts, normal_check_interval, retry_check_interval, notifications_enabled,
            warning, critical, meta_activate
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`;

        await executeQuery(connection, query, [
            i, `Meta_${i}`, "calculated value : %d", "metric.1", "AVE", 0, "2",
            timeperiod, 3, 5, 1, "1", warningThreshold, criticalThreshold, "1"
        ]);
    }

    console.log(`✅ ${count} meta_services inserted.`);
    return ids;
}

// Link services to the host
async function linkHostServiceRelation(connection, hostId, serviceIds) {
    console.log(`🔗 Linking ${serviceIds.length} services to host ID: ${hostId}`);

    for (let serviceId of serviceIds) {
        await executeQuery(connection, 'INSERT INTO host_service_relation (host_host_id, service_service_id) VALUES (?, ?)', [hostId, serviceId]);
    }

    console.log(`✅ Host-service relations created.`);
}

// Main function
async function main() {
    const connection = await connectToDatabase();

    try {
        console.log("🚀 Starting meta services injection");
        const timeperiodIds = await getTimePeriodIds(connection);
        const metaserviceIds = await injectMetaservice(connection, NUMBER_OF_METASERVICES, timeperiodIds);
        const serviceIds = await Promise.all(metaserviceIds.map(id => getOrCreateServiceForMeta(connection, id)));
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
