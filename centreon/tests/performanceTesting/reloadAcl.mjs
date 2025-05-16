import { connectToDatabase } from './dbConfig.mjs';

// Define the period for LDAP update check (in seconds)
const LDAP_UPDATE_PERIOD = 3600;

async function main() {
    const connection = await connectToDatabase();

    try {
        // Check if another instance of centAcl.php is running
        const [rows] = await connection.execute("SELECT COUNT(*) as count FROM cron_operation WHERE name = 'centAcl.php' AND running = '1'");
        if (rows[0].count > 0) {
            console.log("⚠️ Another instance of centAcl.php is currently running. Exiting...");
            return;
        }

        // Mark the operation as running
        await connection.execute("UPDATE cron_operation SET running = '1' WHERE name = 'centAcl.php'");

        // Fetch LDAP settings from the database
        const [ldapSettings] = await connection.execute("SELECT `key`, value FROM options WHERE `key` IN ('ldap_auth_enable', 'ldap_last_acl_update')");
        let ldapEnable = '0';
        let ldapLastUpdate = 0;

        // Loop through the settings and extract values
        ldapSettings.forEach(row => {
            if (row.key === 'ldap_auth_enable') {
                ldapEnable = row.value;
            } else if (row.key === 'ldap_last_acl_update') {
                ldapLastUpdate = row.value;
            }
        });

        // If LDAP is enabled and the last update was too long ago, synchronize
        if (ldapEnable === '1' && ldapLastUpdate < (Date.now() / 1000 - LDAP_UPDATE_PERIOD)) {
            console.log("🔄 Synchronizing with LDAP...");
        } else if (ldapEnable === '1') {
            console.log("✅ LDAP sync is up to date.");
        } else {
            console.log("❌ LDAP synchronization is disabled.");
        }

    } catch (error) {
        // Catch and log any errors
        console.error("🚨 Error:", error.message);
    } finally {
        try {
            // Mark the operation as completed
            await connection.execute("UPDATE cron_operation SET running = '0' WHERE name = 'centAcl.php'");
        } catch (updateError) {
            console.error("⚠️ Error updating the cron operation status:", updateError.message);
        }
        await connection.end();
        console.log("🛑 Connection closed.");
    }
}

// Run the main function and handle any uncaught errors
main().catch(err => console.error("🚨 Error in main execution:", err));
