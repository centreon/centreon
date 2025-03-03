import dotenv from 'dotenv';
import { connectToDatabase } from './dbConfig.mjs';

dotenv.config();

// List of commands to insert
const commands = [
    { id: 1, name: "check_host_alive" },
    { id: 2, name: "check_disk_smb" },
    { id: 3, name: "check_distant_disk_space" },
    { id: 4, name: "check_centreon_dummy" },
    { id: 5, name: "check_centreon_load_average" },
    { id: 6, name: "check_centreon_ping" },
    { id: 7, name: "check_centreon_process" },
    { id: 8, name: "check_centreon_remote_storage" },
    { id: 10, name: "check_centreon_traffic_limited" },
    { id: 11, name: "check_hpjd" },
    { id: 12, name: "check_http" },
    { id: 13, name: "check_https" },
    { id: 14, name: "check_local_swap" },
    { id: 15, name: "check_local_disk" },
    { id: 16, name: "check_local_load" },
    { id: 17, name: "check_local_procs" },
    { id: 18, name: "check_local_users" },
    { id: 19, name: "check_nt_memuse" },
    { id: 20, name: "check_nt_cpu" },
    { id: 21, name: "check_nt_disk" },
    { id: 23, name: "check_tcp" },
    { id: 24, name: "check_nntp" },
    { id: 25, name: "check_pop" },
    { id: 26, name: "check_smtp" },
    { id: 27, name: "check_dns" },
    { id: 28, name: "check_ftp" },
    { id: 29, name: "check_dhcp" },
    { id: 30, name: "check_dig" },
    { id: 31, name: "check_snmp" },
    { id: 32, name: "check_telnet" },
    { id: 33, name: "check_udp" },
    { id: 34, name: "check_centreon_nt" },
    { id: 35, name: "host-notify-by-email" },
    { id: 36, name: "service-notify-by-email" },
    { id: 37, name: "host-notify-by-epager" },
    { id: 38, name: "service-notify-by-epager" },
    { id: 39, name: "submit-host-check-result" },
    { id: 40, name: "submit-service-check-result" },
    { id: 41, name: "process-service-perfdata" },
    { id: 44, name: "check_centreon_uptime" },
    { id: 59, name: "check_centreon_nb_connections" },
    { id: 62, name: "check_centreon_traffic" },
    { id: 76, name: "check_load_average" },
    { id: 77, name: "check_local_cpu_load" },
    { id: 78, name: "check_local_disk_space" },
    { id: 79, name: "check_maxq" },
    { id: 89, name: "host-notify-by-jabber" },
    { id: 90, name: "service-notify-by-jabber" },
    { id: 94, name: "check_centreon_snmp_value" },
    { id: 95, name: "check_centreon_snmp_proc_detailed" },
    { id: 96, name: "check_centreon_cpu" },
    { id: 97, name: "check_centreon_memory" }
];

// Function to insert commands while avoiding duplicates
async function injectCommands(connection) {
    try {
        console.log("🚀 Checking existing commands...");

        // Retrieve existing commands
        const [existingRows] = await connection.execute("SELECT command_id FROM command");
        const existingIds = new Set(existingRows.map(row => row.command_id));

        let values = [];
        let insertCount = 0;

        for (const cmd of commands) {
            if (!existingIds.has(cmd.id)) {
                values.push([cmd.id, cmd.name]);
                insertCount++;
            }
        }

        if (insertCount > 0) {
            const query = "INSERT INTO command (command_id, command_name) VALUES ?";
            await connection.query(query, [values]);
            console.log(`✅ ${insertCount} commands added.`);
        } else {
            console.log("✅ All commands are already present.");
        }

    } catch (error) {
        console.error("❌ Error during insertion:", error);
    }
}

// Main function
async function main() {
    const connection = await connectToDatabase();

    try {
        await injectCommands(connection);
    } catch (error) {
        console.error("❌ An error occurred:", error);
    } finally {
        await connection.end();
    }
}

main();
