import dotenv from 'dotenv';
import { connectToDatabase } from './dbConfig.mjs';

dotenv.config();

// Get the number of services to inject from the environment variable, default to 10 if not specified
const NUMBER_OF_SERVICES = process.env.NUMBER_OF_SERVICES ? parseInt(process.env.NUMBER_OF_SERVICES, 10) : 10;

// List of possible pairs of service_template_model_stm_id and command_command_id
const serviceTemplateCommandPairs = [
    { service_template_model_stm_id: 119, command_command_id: 204 },
    { service_template_model_stm_id: 120, command_command_id: 217 },
    { service_template_model_stm_id: 389, command_command_id: 258 }
];

// Function to retrieve filtered host IDs from the database
async function getFilteredHostIds(connection) {
    const [rows] = await connection.execute("SELECT host_id, host_name FROM host WHERE host_name LIKE 'host_%'");
    console.log("✅ Retrieved filtered hosts:");
    rows.forEach(row => console.log(`Host ID: ${row.host_id}, Host Name: ${row.host_name}`));
    return rows.map(row => row.host_id); // Return only the host IDs
}

// Function to insert a service template (without a command)
async function injectServiceTemplate(connection, service) {
    try {
        const query = `
            INSERT INTO service (service_description, service_alias, service_register, command_command_id)
            VALUES (?, ?, ?, ?)
        `;
        const values = [
            `${service.description}_template`, // Service description
            `${service.alias}_template`, // Service alias
            '0', // Service register set to 0 (for template)
            null  // No command (NULL)
        ];

        const [result] = await connection.execute(query, values);
        const serviceId = result.insertId;

        console.log(`✅ Service template added with ID: ${serviceId}`);
        return serviceId; // Return the inserted service ID
    } catch (error) {
        console.error(`❌ Error inserting service template: ${error.message}`);
        throw error; // If there's an error, throw it to be handled in the main function
    }
}

// Function to inject services with random template-command pairs
async function injectServices(connection, service, properties, injectedIds) {
    const count = properties.service.count; // Number of services to insert
    const serviceTemplateId = await injectServiceTemplate(connection, service); // Insert service template and get its ID

    const [result] = await connection.execute("SELECT MAX(service_id) AS max FROM service");
    let firstId = (result[0].max || 0) + 1; // Start from the max existing service ID + 1
    let maxId = firstId + count; // Calculate the max ID to be used for the new services

    let values = [];
    let insertCount = 0;

    // Loop to inject services
    for (let i = firstId; i < maxId; i++) {
        // Select a random pair of service_template_model_stm_id and command_command_id
        const randomPair = serviceTemplateCommandPairs[Math.floor(Math.random() * serviceTemplateCommandPairs.length)];

        insertCount++;
        values.push([
            i, // service_id
            randomPair.service_template_model_stm_id, // Random service_template_model_stm_id
            `${service.description}_${i}`, // Service description with unique ID
            `${service.alias}_${i}`, // Service alias with unique ID
            '1', // Register the service (set to 1 for active services)
            randomPair.command_command_id // Random command_command_id
        ]);

        // If we have reached 50,000 services to insert, insert them in bulk
        if (insertCount === 50000) {
            await connection.query(`
                INSERT INTO service (service_id, service_template_model_stm_id, service_description, service_alias, service_register, command_command_id)
                VALUES ?`, [values]);

            insertCount = 0; // Reset the insert count
            values = []; // Reset the values array for the next batch
        }
    }

    // Insert any remaining services if necessary
    if (values.length > 0) {
        await connection.query(`
            INSERT INTO service (service_id, service_template_model_stm_id, service_description, service_alias, service_register, command_command_id)
            VALUES ?`, [values]);
    }

    console.log(`✅ ${count} services added with random template-command pairs.`);

    // Call the function to inject service-host relationships after services have been added
    await injectServiceHostRelations(connection, firstId, maxId, injectedIds);
}

// Function to inject relationships between services and hosts
async function injectServiceHostRelations(connection, firstId, maxId, injectedIds) {
    let values = [];
    let insertCount = 0;

    if (injectedIds.host.length === 0) {
        console.error("❌ No matching hosts found!");
        return; // If no hosts are found, exit the function
    }

    // Loop to create service-host relations
    for (let i = firstId; i < maxId; i++) {
        // Select a random host from the injected IDs
        const hostId = injectedIds.host[Math.floor(Math.random() * injectedIds.host.length)];
        insertCount++;
        values.push([hostId, i]); // Create a relation between a host and a service

        // If we've reached 50,000 relations, insert them in bulk
        if (insertCount === 50000) {
            await connection.query(`
                INSERT INTO host_service_relation (host_host_id, service_service_id)
                VALUES ?`, [values]);

            insertCount = 0; // Reset the insert count
            values = []; // Reset the values array for the next batch
        }
    }

    // Insert any remaining host-service relations
    if (values.length > 0) {
        await connection.query(`
            INSERT INTO host_service_relation (host_host_id, service_service_id)
            VALUES ?`, [values]);
    }

    console.log("✅ Service-host relationships added.");
}

// Main function to execute the script
async function main() {
    const connection = await connectToDatabase(); // Connect to the database

    try {
        console.log("🚀 Starting service injection");
        const hostIds = await getFilteredHostIds(connection); // Retrieve filtered host IDs
        console.log("✅ Retrieved host IDs:", hostIds);

        const service = { description: "TestService", alias: "TS" }; // Define the service details
        const properties = {
            service: { count: NUMBER_OF_SERVICES } // Set the number of services to inject
        };
        const injectedIds = {
            host: hostIds // Use the retrieved host IDs for service-host relations
        };

        // Inject the services with the given properties and relations
        await injectServices(connection, service, properties, injectedIds);
    } catch (error) {
        console.error("❌ Error:", error); // Handle any errors that occur
    } finally {
        await connection.end(); // Close the database connection
    }
}

// Execute the script
main();
