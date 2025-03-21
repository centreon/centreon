import { execSync } from 'child_process';
import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config(); // Load environment variables

// 🔹 Check if running locally and retrieve the correct DATABASE_URL
let DATABASE_URL = process.env.DATABASE_URL || '';

if (process.env.LOCAL_RUN === 'true') {
    try {
        console.log("🔍 Searching for database port...");

        const dockerOutput = execSync('docker ps', { encoding: 'utf8' });

        // Find the line containing "bitnami/mariadb"
        const dbContainerLine = dockerOutput
            .split('\n')
            .find(line => line.includes('bitnami/mariadb'));

        if (dbContainerLine) {
            const match = dbContainerLine.match(/0\.0\.0\.0:(\d+)->3306\/tcp/);
            if (match) {
                const dbPort = match[1];
                DATABASE_URL = `mysql://centreon:centreon@127.0.0.1:${dbPort}/centreon`;
                console.log(`✅ Database detected on port ${dbPort}`);
            } else {
                throw new Error("⚠️ Unable to retrieve the MariaDB port.");
            }
        } else {
            throw new Error("⚠️ No MariaDB container found.");
        }
    } catch (error) {
        console.error("❌ Error retrieving the port:", error.message);
        process.exit(1);
    }
}

if (!DATABASE_URL) {
    console.error("❌ DATABASE_URL is not defined. Check your configuration.");
    process.exit(1);
}

// 🔹 Function to establish a database connection
export async function connectToDatabase() {
    const connection = await mysql.createConnection(DATABASE_URL);
    console.log("✅ Successfully connected to the database");
    return connection;
}
