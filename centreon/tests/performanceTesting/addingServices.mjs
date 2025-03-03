import dotenv from 'dotenv';
import { connectToDatabase } from './dbConfig.mjs';

dotenv.config();

const NUMBER_OF_SERVICES = process.env.NUMBER_OF_SERVICES ? parseInt(process.env.NUMBER_OF_SERVICES, 10) : 10;

async function getFilteredHostIds(connection) {
    const [rows] = await connection.execute(
        "SELECT host_id, host_name FROM host WHERE host_name LIKE 'host_%'"
    );
    console.log("✅ Hôtes filtrés récupérés :");
    rows.forEach(row => console.log(`Host ID: ${row.host_id}, Host Name: ${row.host_name}`));
    return rows.map(row => row.host_id);
}

async function injectServiceTemplate(connection, service, properties, injectedIds) {
    const [result] = await connection.execute("SELECT MAX(service_id) AS max FROM service");
    const firstId = ((result[0].max || 0) + 1);

    const query = `
        INSERT INTO service (service_description, service_alias, service_register, command_command_id)
        VALUES (?, ?, ?, ?)
    `;
    const values = [
        `${service.description}_template`,
        `${service.alias}_template`,
        '0',
        injectedIds.command[Math.floor(Math.random() * injectedIds.command.length)]
    ];

    await connection.execute(query, values);

    // Vérification de l'existence du service
    const [checkResult] = await connection.execute("SELECT service_id FROM service WHERE service_id = ?", [firstId]);

    if (checkResult.length === 0) {
        throw new Error(`❌ Échec de l'insertion du service template avec ID: ${firstId}`);
    }

    console.log(`✅ Service template ajouté avec ID: ${firstId}`);

    // Validation de la transaction avant d'ajouter les métriques
    await connection.commit();

    await injectServiceMetrics(connection, firstId, properties);
    return firstId;
}

async function injectServiceMetrics(connection, firstId, properties) {
    const minMetricsCount = properties?.command?.metrics?.min ?? 0;
    const maxMetricsCount = properties?.command?.metrics?.max ?? 10;

    const values = [
        ['$_SERVICEMETRICCOUNT$', Math.floor(Math.random() * (maxMetricsCount - minMetricsCount + 1)) + minMetricsCount, firstId],
        ['$_SERVICEMETRICMINRANGE$', Math.floor(Math.random() * -1000000000), firstId],
        ['$_SERVICEMETRICMAXRANGE$', Math.floor(Math.random() * 1000000000), firstId]
    ];

    const query = `
        INSERT INTO on_demand_macro_service (svc_macro_name, svc_macro_value, svc_svc_id)
        VALUES ?
    `;
    await connection.query(query, [values]);
    console.log(`✅ Metrics ajoutés pour le service ID: ${firstId}`);
}

async function injectServices(connection, service, properties, injectedIds) {
    const ids = [];
    const count = properties.service.count;

    const serviceTemplateId = await injectServiceTemplate(connection, service, properties, injectedIds);

    const [result] = await connection.execute("SELECT MAX(service_id) AS max FROM service");
    let firstId = (result[0].max || 0) + 1;
    let maxId = firstId + count;

    let values = [];
    let insertCount = 0;

    for (let i = firstId; i < maxId; i++) {
        insertCount++;
        values.push([
            i,
            serviceTemplateId,
            `${service.description}_${i}`,
            `${service.alias}_${i}`,
            '1',
            injectedIds.command[Math.floor(Math.random() * injectedIds.command.length)]
        ]);

        if (insertCount === 50000) {
            await connection.query(`
                INSERT INTO service (service_id, service_template_model_stm_id, service_description, service_alias, service_register, command_command_id)
                VALUES ?
            `, [values]);

            insertCount = 0;
            values = [];
        }
    }

    if (values.length > 0) {
        await connection.query(`
            INSERT INTO service (service_id, service_template_model_stm_id, service_description, service_alias, service_register, command_command_id)
            VALUES ?
        `, [values]);
    }

    console.log(`✅ ${count} services ajoutés.`);

    await injectServiceHostRelations(connection, firstId, maxId, injectedIds, ids);
    return ids;
}

async function injectServiceHostRelations(connection, firstId, maxId, injectedIds, ids) {
    let values = [];
    let insertCount = 0;

    if (injectedIds.host.length === 0) {
        console.error("❌ Aucun hôte correspondant trouvé !");
        return;
    }

    for (let i = firstId; i < maxId; i++) {
        const hostId = injectedIds.host[Math.floor(Math.random() * injectedIds.host.length)];
        ids.push({ host_id: hostId, service_id: i });

        insertCount++;
        values.push([hostId, i]);

        if (insertCount === 50000) {
            await connection.query(`
                INSERT INTO host_service_relation (host_host_id, service_service_id)
                VALUES ?
            `, [values]);

            insertCount = 0;
            values = [];
        }
    }

    if (values.length > 0) {
        await connection.query(`
            INSERT INTO host_service_relation (host_host_id, service_service_id)
            VALUES ?
        `, [values]);
    }

    console.log("✅ Relations service-host ajoutées.");
}

async function main() {
    const connection = await connectToDatabase();

    try {
        console.log("🚀 Starting service injection");
        const hostIds = await getFilteredHostIds(connection);
        console.log("✅ Host IDs récupérés :", hostIds);

        const service = { description: "TestService", alias: "TS" };
        const properties = {
            service: { count: NUMBER_OF_SERVICES },
            command: { metrics: { min: 0, max: 10 } }
        };
        const injectedIds = {
            command: [1, 2, 3, 4],
            host: hostIds
        };

        await injectServices(connection, service, properties, injectedIds);
    } catch (error) {
        console.error("❌ Error:", error);
    } finally {
        await connection.end();
    }
}

main();
