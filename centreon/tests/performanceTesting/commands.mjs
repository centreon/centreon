import dotenv from 'dotenv';
import { connectToDatabase } from './dbConfig.mjs';

dotenv.config();

const commands = [
    { id: 1, name: "check_host_alive", command_line: "$USER1$/check_icmp -H $HOSTADDRESS$ -w 3000.0,80% -c 5000.0,100%", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 2, name: "check_disk_smb", command_line: "$USER1$/check_disk_smb -H $HOSTADDRESS$ -s $ARG1$ -u $ARG2$ -p $ARG3$ -w $ARG4$ -c $ARG5$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 3, name: "check_distant_disk_space", command_line: "$USER1$/check_distant_disk_space -H $HOSTADDRESS$ -C $ARG1$ -p $ARG2$ -w $ARG3$ -c $ARG4$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 4, name: "check_centreon_dummy", command_line: "$USER1$/check_centreon_dummy -s $ARG1$ -o $ARG2$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 5, name: "check_centreon_load_average", command_line: "$CENTREONPLUGINS$/centreon_linux_snmp.pl --mode=load --hostname=$HOSTADDRESS$ --warning=$ARG1$ --critical=$ARG2$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 6, name: "check_centreon_ping", command_line: "$USER1$/check_icmp -H $HOSTADDRESS$ -n $ARG1$ -w $ARG2$ -c $ARG3$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 7, name: "check_centreon_process", command_line: "$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$ -v $_HOSTSNMPVERSION$ -C $_HOSTSNMPCOMMUNITY$ -n -p $ARG1$ -w $ARG2$ -c $ARG3$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 8, name: "check_centreon_remote_storage", command_line: "$CENTREONPLUGINS$/centreon_linux_snmp.pl --mode=storage --hostname=$HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 10, name: "check_centreon_traffic_limited", command_line: "$USER1$/check_centreon_snmp_traffic -H $HOSTADDRESS$ -n -i $ARG1$ -w $ARG2$ -c $ARG3$ -T $ARG4$ -v $_HOSTSNMPVERSION$ -C $_HOSTSNMPCOMMUNITY$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 11, name: "check_hpjd", command_line: "$USER1$/check_hpjd -H $HOSTADDRESS$ -C public", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 12, name: "check_http", command_line: "$USER1$/check_http -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 13, name: "check_https", command_line: "$USER1$/check_http -S $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 14, name: "check_local_swap", command_line: "$USER1$/check_swap -w $ARG1$ -c $ARG2$ -v", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 15, name: "check_local_disk", command_line: "$USER1$/check_disk -w $ARG2$ -c $ARG3$ -p $ARG1$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 16, name: "check_local_load", command_line: "$USER1$/check_load -w $ARG1$ -c $ARG2$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 17, name: "check_local_procs", command_line: "$USER1$/check_procs -c $ARG1$ -w $ARG2$ -p $ARG3$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 18, name: "check_local_users", command_line: "$USER1$/check_users -w $ARG1$ -c $ARG2$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 19, name: "check_nt_memuse", command_line: "$USER1$/check_nt -H $HOSTADDRESS$ -p 12489 -v MEMUSE -w $ARG1$ -c $ARG2$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 20, name: "check_nt_cpu", command_line: "$USER1$/check_nt -H $HOSTADDRESS$ -p 12489 -v CPULOAD -w $ARG1$ -c $ARG2$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 21, name: "check_nt_disk", command_line: "$USER1$/check_nt -H $HOSTADDRESS$ -p 12489 -v USEDDISKSPACE -w $ARG1$ -c $ARG2$ -d $ARG3$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 23, name: "check_tcp", command_line: "$USER1$/check_tcp -H $HOSTADDRESS$ -p $ARG1$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 24, name: "check_nntp", command_line: "$USER1$/check_nntp -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 25, name: "check_pop", command_line: "$USER1$/check_pop -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 26, name: "check_smtp", command_line: "$USER1$/check_smtp -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 27, name: "check_dns", command_line: "$USER1$/check_dns -H $HOSTADDRESS$ -d $ARG1$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 28, name: "check_ftp", command_line: "$USER1$/check_ftp -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 29, name: "check_dhcp", command_line: "$USER1$/check_dhcp -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 30, name: "check_dig", command_line: "$USER1$/check_dig -H $HOSTADDRESS$ -d $ARG1$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 31, name: "check_snmp", command_line: "$USER1$/check_snmp -H $HOSTADDRESS$ -o $ARG1$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 32, name: "check_telnet", command_line: "$USER1$/check_telnet -H $HOSTADDRESS$ -p $ARG1$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 33, name: "check_udp", command_line: "$USER1$/check_udp -H $HOSTADDRESS$ -p $ARG1$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 34, name: "check_centreon_nt", command_line: "$USER1$/check_centreon_nt -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 35, name: "host-notify-by-email", command_line: "", command_type: 1, enable_shell: 0 , command_activate: 1 },
    { id: 36, name: "service-notify-by-email", command_line: "", command_type: 1, enable_shell: 0, command_activate: 1 },
    { id: 37, name: "host-notify-by-epager", command_line: "", command_type: 1, enable_shell: 0, command_activate: 1 },
    { id: 38, name: "service-notify-by-epager", command_line: "", command_type: 1, enable_shell: 0, command_activate: 1 },
    { id: 39, name: "submit-host-check-result", command_line: "", command_type: 1, enable_shell: 0, command_activate: 1 },
    { id: 40, name: "submit-service-check-result", command_line: "", command_type: 1, enable_shell: 0, command_activate: 1 },
    { id: 41, name: "process-service-perfdata", command_line: "", command_type: 1, enable_shell: 0, command_activate: 1 },
    { id: 44, name: "check_centreon_uptime", command_line: "$USER1$/check_centreon_uptime -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 59, name: "check_centreon_nb_connections", command_line: "$USER1$/check_centreon_nb_connections -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 62, name: "check_centreon_traffic", command_line: "$USER1$/check_centreon_traffic -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 76, name: "check_load_average", command_line: "$USER1$/check_load -w $ARG1$ -c $ARG2$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 77, name: "check_local_cpu_load", command_line: "$USER1$/check_cpu -w $ARG1$ -c $ARG2$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 78, name: "check_local_disk_space", command_line: "$USER1$/check_disk -w $ARG1$ -c $ARG2$ -p $ARG3$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 79, name: "check_maxq", command_line: "$USER1$/check_maxq -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 89, name: "host-notify-by-jabber", command_line: "", command_type: 1, enable_shell: 0, command_activate: 1 },
    { id: 90, name: "service-notify-by-jabber", command_line: "", command_type: 1, enable_shell: 0, command_activate: 1 },
    { id: 94, name: "check_centreon_snmp_value", command_line: "$USER1$/check_centreon_snmp_value -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 95, name: "check_centreon_snmp_proc_detailed", command_line: "$USER1$/check_centreon_snmp_proc_detailed -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 96, name: "check_centreon_cpu", command_line: "$USER1$/check_centreon_cpu -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 },
    { id: 97, name: "check_centreon_memory", command_line: "$USER1$/check_centreon_memory -H $HOSTADDRESS$", command_type: 2, enable_shell: 0, command_activate: 1 }
];

// Function to display and insert commands
async function injectCommands(connection) {
    try {
        console.log("🚀 Checking existing commands...");

        // Retrieve existing commands from the database
        const [existingRows] = await connection.execute("SELECT command_id, command_name FROM command");

        if (existingRows.length > 0) {
            console.log("📌 List of existing commands:");
            existingRows.forEach(row => {
                console.log(`🔹 ID: ${row.command_id}, Name: ${row.command_name}`);
            });
        } else {
            console.log("⚠️ No existing commands found.");
        }

        // Identify commands to insert
        const existingIds = new Set(existingRows.map(row => row.command_id));
        let values = [];
        let insertCount = 0;

        for (const cmd of commands) {
            if (!existingIds.has(cmd.id)) {
                values.push([cmd.id, cmd.name]);
                insertCount++;
            }
        }

        // Insert only new commands
        if (insertCount > 0) {
            const query = "INSERT INTO command (command_id, command_name) VALUES ?";
            await connection.query(query, [values]);
            console.log(`✅ ${insertCount} new commands added.`);
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

// Run the script
main();
