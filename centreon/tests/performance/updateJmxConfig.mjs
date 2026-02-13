import fs from 'fs';
import path from 'path';

// Get configuration from environment variables
const config = {
  jmeterDomain: process.env.JMETER_DOMAIN || '',
  jmeterPort: process.env.JMETER_PORT || '',
  jmeterProtocol: process.env.JMETER_PROTOCOL || '',
  apiPrefix: process.env.API_PREFIX || '',
  numberOfUsers: process.env.NUMBER_OF_USERS || '',
  rampTime: process.env.RAMP_TIME || ''
};

const jmxFolder = process.env.JMX_FOLDER || path.join(process.cwd(), 'jmeterFolder');

console.log('🔧 JMX Configuration Update Script');
console.log('Configuration:', config);
console.log('JMX Folder:', jmxFolder);

/**
 * Update JMX file content with the provided configuration
 * Excludes "Map" ThreadGroup from num_threads and ramp_time changes
 */
function updateJmxContent(content) {
  let updatedContent = content;

  // Update port (empty if not provided)
  updatedContent = updatedContent.replace(
    /<stringProp name="HTTPSampler\.port">.*?<\/stringProp>/g,
    `<stringProp name="HTTPSampler.port">${config.jmeterPort}</stringProp>`
  );

  // Update domain
  if (config.jmeterDomain) {
    updatedContent = updatedContent.replace(
      /<stringProp name="HTTPSampler\.domain">.*?<\/stringProp>/g,
      `<stringProp name="HTTPSampler.domain">${config.jmeterDomain}</stringProp>`
    );
  }

  // Update protocol
  if (config.jmeterProtocol) {
    updatedContent = updatedContent.replace(
      /<stringProp name="HTTPSampler\.protocol">.*?<\/stringProp>/g,
      `<stringProp name="HTTPSampler.protocol">${config.jmeterProtocol}</stringProp>`
    );
  }

  // Update API prefix in paths
  if (config.apiPrefix) {
    updatedContent = updatedContent.replace(
      /(<stringProp name="HTTPSampler\.path">\/)[^/]*\/api\/latest/g,
      `$1${config.apiPrefix}/api/latest`
    );
  }

  // Update ChromeDriver arguments (add headless options)
  updatedContent = updatedContent.replace(
    /<collectionProp name="ChromeDriverConfig\.arguments">(?!<stringProp name="">--headless=new<\/stringProp>)/g,
    '<collectionProp name="ChromeDriverConfig.arguments"><stringProp name="">--headless=new</stringProp><stringProp name="">--no-sandbox</stringProp><stringProp name="">--disable-dev-shm-usage</stringProp>'
  );

  // Update num_threads and ramp_time for all ThreadGroups EXCEPT "Map"
  if (config.numberOfUsers || config.rampTime) {
    updatedContent = updateThreadGroupsExceptMap(updatedContent);
  }

  return updatedContent;
}

/**
 * Update ThreadGroup settings (num_threads and ramp_time) for all groups except "Map"
 */
function updateThreadGroupsExceptMap(content) {
  const lines = content.split('\n');
  let inMapGroup = false;
  let mapGroupDepth = 0;

  const updatedLines = lines.map((line, index) => {
    // Detect entering Map ThreadGroup
    if (line.includes('ThreadGroup') && line.includes('testname="Map"')) {
      inMapGroup = true;
      mapGroupDepth = 0;
      return line;
    }

    // Track depth within Map ThreadGroup using </ThreadGroup> or next ThreadGroup
    if (inMapGroup) {
      if (line.includes('</ThreadGroup>')) {
        mapGroupDepth++;
        if (mapGroupDepth >= 1) {
          inMapGroup = false;
        }
      }
      // If we encounter another ThreadGroup definition, we've left Map
      if (line.includes('<ThreadGroup') && line.includes('testname=') && !line.includes('testname="Map"')) {
        inMapGroup = false;
      }
    }

    // Update num_threads only if NOT in Map group
    if (!inMapGroup && config.numberOfUsers && line.includes('<intProp name="ThreadGroup.num_threads">')) {
      return line.replace(
        /<intProp name="ThreadGroup\.num_threads">.*?<\/intProp>/,
        `<intProp name="ThreadGroup.num_threads">${config.numberOfUsers}</intProp>`
      );
    }

    // Update ramp_time only if NOT in Map group
    if (!inMapGroup && config.rampTime && line.includes('<intProp name="ThreadGroup.ramp_time">')) {
      return line.replace(
        /<intProp name="ThreadGroup\.ramp_time">.*?<\/intProp>/,
        `<intProp name="ThreadGroup.ramp_time">${config.rampTime}</intProp>`
      );
    }

    return line;
  });

  return updatedLines.join('\n');
}

/**
 * Process all JMX files in the folder
 */
function processJmxFiles() {
  const jmxFiles = fs.readdirSync(jmxFolder).filter(file => file.endsWith('.jmx'));

  if (jmxFiles.length === 0) {
    console.error('❌ No JMX files found in:', jmxFolder);
    process.exit(1);
  }

  console.log(`📁 Found ${jmxFiles.length} JMX file(s)`);

  for (const jmxFile of jmxFiles) {
    const filePath = path.join(jmxFolder, jmxFile);
    console.log(`\n📝 Processing: ${jmxFile}`);

    try {
      const content = fs.readFileSync(filePath, 'utf-8');
      const updatedContent = updateJmxContent(content);
      fs.writeFileSync(filePath, updatedContent, 'utf-8');
      console.log(`✅ Updated: ${jmxFile}`);
    } catch (error) {
      console.error(`❌ Error processing ${jmxFile}:`, error.message);
      process.exit(1);
    }
  }

  console.log('\n✅ All JMX files updated successfully!');
}

// Run the script
processJmxFiles();
