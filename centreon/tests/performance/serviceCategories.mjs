import dotenv from 'dotenv';
import { connectToDatabase } from './dbConfig.mjs';

dotenv.config(); // 🔹 Load environment variables first

// 🔹 Function to generate a random integer between min and max
function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
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

// Retrieve existing services from the database
async function getServiceIds(connection) {
    const rows = await executeQuery(connection, 'SELECT service_id, service_description FROM service');
    console.log("🔍 Services:", rows);
    return rows.map(row => ({
        service_id: row.service_id,
        service_description: row.service_description
    }));
}

// Inject service categories
async function injectServiceCategory(connection, serviceCategory, properties) {
    const ids = [];

    const count = parseInt(process.env.NUMBER_OF_SERVICES_CATEGORIS, 10); // Get the number of categories from .env

    const result = await executeQuery(connection, 'SELECT MAX(sc_id) AS max FROM service_categories');
    const firstId = (parseInt(result[0].max, 10) || 0) + 1;
    const maxId = firstId + count;

    const baseQuery = 'INSERT INTO service_categories (sc_id, sc_name, sc_description) VALUES ';
    let valuesQuery = '';
    let insertCount = 0;
    const name = serviceCategory.name + '_';
    const alias = serviceCategory.alias + '_';

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

    console.log(`✅ Service categories inserted`);

    return ids;
}

// Inject relations between services and service categories
async function injectServiceCategoryRelation(connection, categoryIds, serviceIds, minServicesCount, maxServicesCount) {
    const baseQuery = 'INSERT INTO service_categories_relation (sc_id, service_service_id) VALUES ';
    let valuesQuery = '';
    let insertCount = 0;

    for (let categoryId of categoryIds) {
        const hostCount = randomInt(minServicesCount, maxServicesCount);

        for (let i = 0; i < hostCount; i++) {
            insertCount++;
            const randomService = serviceIds[Math.floor(Math.random() * serviceIds.length)].service_id;
            valuesQuery += `(${categoryId}, ${randomService}),`;

            if (insertCount === 50000) {
                await executeQuery(connection, baseQuery + valuesQuery.slice(0, -1));
                insertCount = 0;
                valuesQuery = '';
            }
        }
    }

    if (valuesQuery.length > 0) {
        await executeQuery(connection, baseQuery + valuesQuery.slice(0, -1));
    }

    console.log(`✅ Relations between categories and services created`);
}

// Main execution
async function main() {
    const connection = await connectToDatabase();

    const serviceCategory = {
        name: 'MyServiceCategory',
        alias: 'MyServiceCategoryAlias'
    };

    const properties = {
        service_category: {
            count: parseInt(process.env.NUMBER_OF_SERVICES_CATEGORIS, 10),  // Using the environment variable
            services: {
                min: 1,  // Minimum number of services associated with each category
                max: 5   // Maximum number of services associated with each category
            }
        }
    };

    try {
        // Retrieve available services
        const services = await getServiceIds(connection);
        if (services.length === 0) {
            console.log("❌ No services available in the database. Make sure services exist.");
            return;
        }

        console.log(`✅ Available services: ${services.length} services found.`);

        const categoryIds = await injectServiceCategory(connection, serviceCategory, properties);
        const minServicesCount = properties['service_category']['services']['min'] || 0;
        const maxServicesCount = properties['service_category']['services']['max'] || 5;
        await injectServiceCategoryRelation(connection, categoryIds, services, minServicesCount, maxServicesCount);

    } catch (error) {
        console.error("❌ Error during the service category injection", error);
    } finally {
        await connection.end();
        console.log("🔻 Connection closed.");
    }
}

main();
