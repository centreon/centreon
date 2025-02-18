import dotenv from 'dotenv';
import { connectToDatabase } from './dbConfig.mjs';

dotenv.config();

let NUMBER_OF_HOSTS = process.env.NUMBER_OF_HOSTS ? parseInt(process.env.NUMBER_OF_HOSTS, 10) : 2;

// ✅ Insert host_id into the extended_host_information table
async function injectHostIdsInExtendedHostInformation(connection, hostIds) {
    try {
        const extendedHostInfoQuery = `INSERT INTO extended_host_information (host_host_id) VALUES `;

        const values = hostIds.map(hostId => `(${hostId})`).join(', ');

        // Complete query to insert all host_ids
        const fullQuery = extendedHostInfoQuery + values;

        // Execute the insert query
        await connection.execute(fullQuery);

        console.log(`✅ ${hostIds.length} host_ids inserted into the extended_host_information table.`);
    } catch (error) {
        console.error('❌ Error inserting host_ids into extended_host_information:', error);
        throw error; // Throw the error for further handling
    }
}

// ✅ Insert hosts into the "host" table
async function injectHosts(connection, host, properties, injectedIds) {
    const ids = [];
    const count = properties['host']['count'];

    try {
        // Get the max existing host_id
        const [result] = await connection.execute('SELECT MAX(host_id) AS max FROM host');
        const firstId = (result[0].max || 0) + 1;
        const maxId = firstId + count;

        // Prepare the insertion query
        const baseQuery = `INSERT INTO host (
            host_id, host_name, host_alias, host_address, host_register, command_command_id,
            command_command_id_arg1, host_template_model_htm_id, host_process_perf_data,
            host_retain_status_information, host_retain_nonstatus_information, host_notifications_enabled,
            host_flap_detection_enabled, host_event_handler_enabled, host_obsess_over_host,
            host_check_freshness, host_active_checks_enabled, host_passive_checks_enabled,
            host_checks_enabled, host_snmp_version
        ) VALUES `;

        const valuesQuery = [];
        const name = `${host.name}_`;
        const alias = `${host.alias}_`;
        const address = host.address;

        // Default values for the new columns (set to 3)
        const defaultValues = [
            3, // host_process_perf_data
            3, // host_retain_status_information
            3, // host_retain_nonstatus_information
            3, // host_notifications_enabled
            3, // host_flap_detection_enabled
            3, // host_event_handler_enabled
            3, // host_obsess_over_host
            3, // host_check_freshness
            3, // host_active_checks_enabled
            3, // host_passive_checks_enabled
            3  // host_checks_enabled
        ];

        const commandArgOptions = ["!0!OK", "!1!Warning", "!2!Critical"];
        const templateModelHtmId = 3; // Default template model

        for (let i = firstId; i < maxId; i++) {
            ids.push(i);

            // Select a random command_command_id_arg1 value
            const randomCommandArg = commandArgOptions[Math.floor(Math.random() * commandArgOptions.length)];

            // Add the values to insert for each host
            valuesQuery.push(`(${i}, "${name}${i}", "${alias}${i}", "${address}", "1", 4, "${randomCommandArg}", ${templateModelHtmId}, ${defaultValues.join(", ")}, 0)`);
        }

        // Complete query with all values
        const fullQuery = baseQuery + valuesQuery.join(', ');

        // Execute insertion query
        await connection.execute(fullQuery);

        console.log(`✅ ${count} hosts inserted.`);

        // Insert host-server relations
        await injectHostServerRelations(connection, firstId, maxId, injectedIds);

        // Insert all host IDs into the extended_host_information table
        await injectHostIdsInExtendedHostInformation(connection, ids);

        return ids;
    } catch (error) {
        console.error('❌ Error inserting hosts:', error);
        throw error; // Throw error for further handling
    }
}

// ✅ Insert host-server relationships
async function injectHostServerRelations(connection, firstId, maxId, injectedIds) {
    const [pollers] = await connection.execute('SELECT id FROM nagios_server');
    if (pollers.length === 0) {
        console.error("❌ No Nagios server found in the database!");
        return;
    }

    const validNagiosServerIds = pollers.map(row => row.id); // List of Nagios server IDs

    const relationQuery = `INSERT INTO ns_host_relation (nagios_server_id, host_host_id) VALUES `;
    let relationValues = [];
    let insertCount = 0;

    for (let i = firstId; i < maxId; i++) {
        insertCount++;

        // Randomly select a valid poller from existing Nagios servers
        const nagiosServerId = validNagiosServerIds[Math.floor(Math.random() * validNagiosServerIds.length)];
        relationValues.push(`(${nagiosServerId}, ${i})`);

        if (insertCount === 50000) {
            await connection.execute(relationQuery + relationValues.join(', '));
            insertCount = 0;
            relationValues = [];
        }
    }

    if (relationValues.length > 0) {
        await connection.execute(relationQuery + relationValues.join(', '));
    }

    console.log(`✅ Host-server relationships inserted.`);
}

// ✅ Main function
async function main() {
    const connection = await connectToDatabase();
    try {
        console.log("🚀 Starting host injection");

        const host = { name: 'host', alias: 'HostAlias', address: '127.0.0.1' };
        const properties = { host: { count: NUMBER_OF_HOSTS } };
        const injectedIds = {
            command: [1], // ID of an existing command
            poller: [1, 2, 3] // IDs of available pollers
        };

        await injectHosts(connection, host, properties, injectedIds);
        console.log("✅ All hosts have been successfully inserted.");
    } catch (error) {
        console.error("❌ Error inserting hosts:", error);
    } finally {
        await connection.end();
        console.log("🔻 Connection closed.");
    }
}

main();
