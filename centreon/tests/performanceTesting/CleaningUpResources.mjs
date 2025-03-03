import { connectToDatabase } from './dbConfig.mjs';

async function purgeDatabase() {
    const connection = await connectToDatabase();

    try {
        console.log("🔧 Disabling foreign key checks...");
        await connection.query('SET FOREIGN_KEY_CHECKS=0;');

        console.log("🗑️ Purging filtered data...");

        // 🔹 Retrieve the first 20 rows from `host_service_relation` to avoid deleting them
        const [first20Rows] = await connection.query("SELECT hsr_id FROM host_service_relation LIMIT 20;");
        const idsToKeep = first20Rows.map(row => row.hsr_id);

        // 🔹 Delete host-service relations, except for the first 20 rows
        if (idsToKeep.length > 0) {
            const [resultHostServiceRelations] = await connection.query(
                `DELETE FROM host_service_relation WHERE hsr_id NOT IN (${idsToKeep.join(",")});`
            );
            console.log(`✅ Deleted ${resultHostServiceRelations.affectedRows} host-service relations, excluding the first 20.`);
        } else {
            console.log("⚠️ No host-service relations found to keep. Skipping deletion.");
        }

        // 🔹 Delete host-server relations
        const [resultHostServerRelations] = await connection.query(
            "DELETE FROM ns_host_relation WHERE host_host_id IN (SELECT host_id FROM host WHERE host_name LIKE 'host_%');"
        );
        console.log(`✅ Deleted ${resultHostServerRelations.affectedRows} host-server relations.`);

        // 🔹 Delete entries from extended_host_information
        const [resultExtendedInfo] = await connection.query(
            "DELETE FROM extended_host_information WHERE host_host_id IN (SELECT host_id FROM host WHERE host_name LIKE 'host_%');"
        );
        console.log(`✅ Deleted ${resultExtendedInfo.affectedRows} extended host information.`);

        // 🔹 Delete only services starting with 'TestService_'
        const [resultTestService] = await connection.query(
            "DELETE FROM service WHERE service_description LIKE 'TestService_%' OR service_alias LIKE 'TestService_%';"
        );
        console.log(`✅ Deleted ${resultTestService.affectedRows} services starting with 'TestService_'`);

        // 🔹 Delete other normal services (not starting with 'TestService_')
        const [resultServices] = await connection.query(
            "DELETE FROM service WHERE (service_description LIKE 'service_%' OR service_alias LIKE 'service_%') " +
            "AND service_description NOT LIKE 'TestService_%' AND service_alias NOT LIKE 'TestService_%';"
        );
        console.log(`✅ Deleted ${resultServices.affectedRows} other services.`);

        // 🔹 Delete hosts
        const [resultHosts] = await connection.query("DELETE FROM host WHERE host_name LIKE 'host_%';");
        console.log(`✅ Deleted ${resultHosts.affectedRows} hosts.`);

        // 🔹 Delete host groups
        const [resultHostGroups] = await connection.query("DELETE FROM hostgroup WHERE hg_name LIKE 'group_%';");
        console.log(`✅ Deleted ${resultHostGroups.affectedRows} host groups.`);

        // 🔹 Delete meta services
        const [resultMetaServices] = await connection.query("DELETE FROM meta_service WHERE meta_name LIKE 'Meta_%';");
        console.log(`✅ Deleted ${resultMetaServices.affectedRows} meta services.`);

        // 🔹 Empty servicegroup_relation table
        const [resultServiceGroupRelations] = await connection.query("DELETE FROM servicegroup_relation;");
        console.log(`✅ Deleted ${resultServiceGroupRelations.affectedRows} service group relations.`);

        // 🔹 Delete service groups with name starting with 'MyServiceGroup_'
        const [resultServiceGroups] = await connection.query("DELETE FROM servicegroup WHERE sg_name LIKE 'MyServiceGroup_%';");
        console.log(`✅ Deleted ${resultServiceGroups.affectedRows} service groups starting with 'MyServiceGroup_'.`);

        console.log("🔄 Re-enabling foreign key checks...");
        await connection.query('SET FOREIGN_KEY_CHECKS=1;');

        console.log("🎉 Database purge completed successfully.");
    } catch (error) {
        console.error(`❌ Error during database purge: ${error.message}`);
    } finally {
        await connection.end();
        console.log("🔌 Database connection closed.");
    }
}

// Execute the script
purgeDatabase();
