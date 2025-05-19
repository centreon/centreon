import { exec } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

// Convert `import.meta.url` to an absolute path
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// List of scripts to execute
const scripts = ["injectingUsers.mjs", "hosts.mjs", "services.mjs", "metaservices.mjs", "hostGroup.mjs", "serviceGroups.mjs","reloadAclApplyConfig.mjs"];

// Function to execute a script using Node.js
function runScript(script) {
    return new Promise((resolve, reject) => {
        const scriptPath = path.join(__dirname, script);
        exec(`node ${scriptPath}`, (error, stdout, stderr) => {
            if (error) {
                console.error(`❌ Error in ${script}:\n`, stderr);
                reject(error);
            } else {
                console.log(`✅ Output of ${script}:\n`, stdout);
                resolve(stdout);
            }
        });
    });
}

// Execute all scripts sequentially
(async () => {
    for (const script of scripts) {
        try {
            await runScript(script);
        } catch (error) {
            console.error(`Error while executing ${script}`);
        }
    }
})();
