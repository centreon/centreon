#!/usr/bin/env node
/**
 * Script to update MAP server settings (domain and port) in JMX files
 * Only updates HTTPSamplerProxy blocks that contain /centreon-map/ in the path
 *
 * Usage: node updateMapSettings.mjs <jmx_file> [--domain <domain>] [--port <port>]
 */

import { readFileSync, writeFileSync } from 'fs';

function parseArgs() {
  const args = process.argv.slice(2);
  const result = { jmxFile: null, domain: null, port: null };

  if (args.length === 0) {
    console.error('Usage: node updateMapSettings.mjs <jmx_file> [--domain <domain>] [--port <port>]');
    process.exit(1);
  }

  result.jmxFile = args[0];

  for (let i = 1; i < args.length; i++) {
    if (args[i] === '--domain' && args[i + 1]) {
      result.domain = args[++i];
    } else if (args[i] === '--port' && args[i + 1]) {
      result.port = args[++i];
    }
  }

  return result;
}

function updateMapSettings(content, domain, port) {
  // Match HTTPSamplerProxy blocks and update only those containing /centreon-map/
  const httpSamplerRegex = /<HTTPSamplerProxy[^>]*>[\s\S]*?<\/HTTPSamplerProxy>/g;

  return content.replace(httpSamplerRegex, (block) => {
    // Check if this block contains /centreon-map/ in the path
    if (!block.includes('/centreon-map/')) {
      return block;
    }

    let updatedBlock = block;

    if (domain) {
      updatedBlock = updatedBlock.replace(
        /(<stringProp name="HTTPSampler\.domain">)[^<]*/,
        `$1${domain}`
      );
    }

    if (port) {
      updatedBlock = updatedBlock.replace(
        /(<stringProp name="HTTPSampler\.port">)[^<]*/,
        `$1${port}`
      );
    }

    return updatedBlock;
  });
}

function main() {
  const { jmxFile, domain, port } = parseArgs();

  if (!domain && !port) {
    console.log('No domain or port specified, nothing to update.');
    process.exit(0);
  }

  console.log(`Processing ${jmxFile}...`);
  console.log(`  MAP domain: ${domain || '(not changing)'}`);
  console.log(`  MAP port: ${port || '(not changing)'}`);

  const content = readFileSync(jmxFile, 'utf-8');
  const updatedContent = updateMapSettings(content, domain, port);

  writeFileSync(jmxFile, updatedContent, 'utf-8');
  console.log(`MAP settings updated in ${jmxFile}`);
}

main();
