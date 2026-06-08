import fs from 'fs';
import path from 'path';

// 1. Get configuration from environment variables (GitHub Actions inputs)
const config = {
  jmeterDomain: process.env.JMETER_DOMAIN || '',
  jmeterPort: process.env.JMETER_PORT || '',
  jmeterProtocol: process.env.JMETER_PROTOCOL || '',
  apiPrefix: process.env.API_PREFIX || '',
  numberOfUsers: process.env.NUMBER_OF_USERS || '',
  rampTime: process.env.RAMP_TIME || ''
};

const jmxFolder = process.env.JMX_FOLDER || path.join(process.cwd(), 'jmeterFolder');

/**
 * Update JMX file content
 * COMPLETELY ignores the Map ThreadGroup AND its associated hashTree
 * Structure: <ThreadGroup testname="Map">...</ThreadGroup><hashTree>...samplers...</hashTree>
 */
function updateJmxContent(content) {
  const lines = content.split('\n');
  let inMapGroup = false;
  let inMapHashTree = false;
  let hashTreeDepth = 0;

  const updatedLines = lines.map((line) => {
    // Detect entry into Map ThreadGroup
    if (line.includes('<ThreadGroup') && line.includes('testname="Map"')) {
      inMapGroup = true;
      return line;
    }

    // Detect exit of Map ThreadGroup - next we expect its hashTree
    if (inMapGroup && line.includes('</ThreadGroup>')) {
      inMapGroup = false;
      inMapHashTree = true; // The next hashTree belongs to Map
      hashTreeDepth = 0;
      return line;
    }

    // Track hashTree depth for the Map group
    if (inMapHashTree) {
      if (line.includes('<hashTree>') || line.match(/<hashTree\s*\/>/)) {
        hashTreeDepth++;
      }
      if (line.includes('</hashTree>')) {
        hashTreeDepth--;
        if (hashTreeDepth === 0) {
          inMapHashTree = false; // Exiting Map's hashTree
        }
      }
      return line; // Don't modify anything in Map's hashTree
    }

    // IF WE ARE IN MAP ThreadGroup definition: DO NOTHING
    if (inMapGroup) {
      return line;
    }

    // IF WE ARE OUTSIDE MAP: APPLY REPLACEMENTS
    let updatedLine = line;

    // Update Port
    if (updatedLine.includes('HTTPSampler.port')) {
      updatedLine = updatedLine.replace(
        /<stringProp name="HTTPSampler\.port">.*?<\/stringProp>/,
        `<stringProp name="HTTPSampler.port">${config.jmeterPort}</stringProp>`
      );
    }

    // Update Domain
    if (config.jmeterDomain && updatedLine.includes('HTTPSampler.domain')) {
      updatedLine = updatedLine.replace(
        /<stringProp name="HTTPSampler\.domain">.*?<\/stringProp>/,
        `<stringProp name="HTTPSampler.domain">${config.jmeterDomain}</stringProp>`
      );
    }

    // Update Protocol
    if (config.jmeterProtocol && updatedLine.includes('HTTPSampler.protocol')) {
      updatedLine = updatedLine.replace(
        /<stringProp name="HTTPSampler\.protocol">.*?<\/stringProp>/,
        `<stringProp name="HTTPSampler.protocol">${config.jmeterProtocol}</stringProp>`
      );
    }

    // Update API prefix in paths
    if (config.apiPrefix && updatedLine.includes('HTTPSampler.path')) {
      updatedLine = updatedLine.replace(
        /(<stringProp name="HTTPSampler\.path">\/)[^/]*\/api\/latest/,
        `$1${config.apiPrefix}/api/latest`
      );
    }

    // Update Threads & Ramp-up
    if (config.numberOfUsers && updatedLine.includes('ThreadGroup.num_threads')) {
      updatedLine = updatedLine.replace(
        /<intProp name="ThreadGroup\.num_threads">.*?<\/intProp>/,
        `<intProp name="ThreadGroup.num_threads">${config.numberOfUsers}</intProp>`
      );
    }

    if (config.rampTime && updatedLine.includes('ThreadGroup.ramp_time')) {
      updatedLine = updatedLine.replace(
        /<intProp name="ThreadGroup\.ramp_time">.*?<\/intProp>/,
        `<intProp name="ThreadGroup.ramp_time">${config.rampTime}</intProp>`
      );
    }

    return updatedLine;
  });

  // Handle ChromeDriver (usually unique and outside ThreadGroups)
  let result = updatedLines.join('\n');
  result = result.replace(
    /<collectionProp name="ChromeDriverConfig\.arguments">(?!<stringProp name="">--headless=new<\/stringProp>)/g,
    '<collectionProp name="ChromeDriverConfig.arguments"><stringProp name="">--headless=new</stringProp><stringProp name="">--no-sandbox</stringProp><stringProp name="">--disable-dev-shm-usage</stringProp>'
  );

  return result;
}

/**
 * Main execution function
 */
function processFiles() {
  if (!fs.existsSync(jmxFolder)) {
    console.error(`❌ Folder not found: ${jmxFolder}`);
    process.exit(1);
  }

  const files = fs.readdirSync(jmxFolder).filter(f => f.endsWith('.jmx'));

  files.forEach(file => {
    const filePath = path.join(jmxFolder, file);
    const content = fs.readFileSync(filePath, 'utf8');
    const updated = updateJmxContent(content);
    fs.writeFileSync(filePath, updated, 'utf8');
    console.log(`✅ ${file} updated (Map Group preserved)`);
  });
}

processFiles();