import dotenv from 'dotenv';
import { connectToDatabase } from './dbConfig.mjs';

dotenv.config();

const NUMBER_OF_HOSTGROUPS = process.env.NUMBER_OF_HOSTGROUPS
    ? parseInt(process.env.NUMBER_OF_HOSTGROUPS, 10)
    : 10;

// ✅ Retrieve valid host IDs from the database
async function getValidHostIds(connection) {
    const [rows] = await connection.execute('SELECT host_id FROM host');
    return rows.map(row => row.host_id);
}

// ✅ Retrieve existing hostgroups to avoid duplicates
async function getExistingHostgroups(connection) {
    const [rows] = await connection.execute('SELECT hg_name FROM hostgroup');
    return new Set(rows.map(row => row.hg_name));
}

// ✅ Insert hostgroups in bulk while avoiding duplicates
async function injectHostgroups(connection, hostgroup, properties, injectedIds) {
    const ids = [];
    const count = properties['hostgroup'].count;
    const existingHostgroups = await getExistingHostgroups(connection);

    // Retrieve the current max ID
    const [result] = await connection.execute('SELECT MAX(hg_id) AS max FROM hostgroup');
    const firstId = (result[0].max || 0) + 1;
    const maxId = firstId + count;

    const values = [];
    let skippedCount = 0;

    for (let i = firstId; i < maxId; i++) {
        const name = `${hostgroup.name}_${i}`;
        const alias = `${hostgroup.alias}_${i}`;

        if (existingHostgroups.has(name)) {
            skippedCount++;
            continue;
        }

        ids.push(i);
        values.push([i, name, alias]);
    }

    if (values.length > 0) {
        const sql = 'INSERT INTO hostgroup (hg_id, hg_name, hg_alias) VALUES ?';
        await connection.query(sql, [values]);
        console.log(`✅ ${values.length} new hostgroups inserted.`);
    }

    if (skippedCount > 0) {
        console.log(`⚠️ ${skippedCount} hostgroups were already existing and were not inserted.`);
    }

    // ✅ Insert hostgroup-host relations
    await injectHostgroupRelations(connection, ids, injectedIds);

    return ids;
}

// ✅ Insert relations between hostgroups and hosts
async function injectHostgroupRelations(connection, hostgroupIds, injectedIds) {
    const validHostIds = await getValidHostIds(connection);
    const hostIds = injectedIds.host.filter(id => validHostIds.includes(id));

    if (hostIds.length === 0) {
        console.log("⚠️ No valid hosts found, relation insertion canceled.");
        return;
    }

    const values = [];
    const minHosts = 1, maxHosts = 5;

    for (const hgId of hostgroupIds) {
        const hostCount = Math.floor(Math.random() * (maxHosts - minHosts + 1)) + minHosts;
        for (let i = 0; i < hostCount; i++) {
            const hostId = hostIds[Math.floor(Math.random() * hostIds.length)];
            values.push([hgId, hostId]);
        }
    }

    if (values.length > 0) {
        const sql = 'INSERT INTO hostgroup_relation (hostgroup_hg_id, host_host_id) VALUES ?';
        await connection.query(sql, [values]);
        console.log(`✅ Hostgroup-host relations inserted (${values.length} records).`);
    }
}

// ✅ Main function
async function main() {
    const connection = await connectToDatabase();

    try {

        console.log("🚀 Starting host group injection");
        // Example parameters
        const hostgroup = { name: "group", alias: "alias" };
        const properties = { hostgroup: { count: NUMBER_OF_HOSTGROUPS, hosts: { min: 1, max: 5 } } };
        const injectedIds = { host: Array.from({ length: 50 }, (_, i) => i + 1) };

        // Insert hostgroups and relations
        await injectHostgroups(connection, hostgroup, properties, injectedIds);

        console.log("✅ Operation completed.");
    } catch (error) {
        console.error("❌ Error:", error.message);
    } finally {
        console.log("🔻 Script finished");
        await connection.end();
    }
}

main();
