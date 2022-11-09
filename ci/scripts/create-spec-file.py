#!/usr/bin/env python3

import json
from sys import argv

package_name = argv[0]

with open('ci/packaging/plugin.head.spectemplate', 'r') as rfile:
    pluginspec = rfile.read()

with open('ci/packaging/plugin.body.spectemplate', 'r') as rfile:
    pluginbody = rfile.read()

specfile = pluginspec + pluginbody

with open('ci/packaging/%s/pkg.json' % package_name, 'r') as rfile:
    plugincfg = json.load(rfile)

with open('ci/packaging/%s/rpm.json' % package_name, 'r') as rfile:
    pluginrpm = json.load(rfile)

specfile = specfile.replace(
    '@NAME@', plugincfg['pkg_name'].replace('centreon-plugin-', '')
)
specfile = specfile.replace('@SUMMARY@', plugincfg['pkg_summary'])
specfile = specfile.replace('@PLUGIN_NAME@', plugincfg['plugin_name'])
specfile = specfile.replace(
    '@REQUIRES@',
    "\n".join(["Requires:\t%s" % x for x in pluginrpm.get('dependencies', '')])
)
specfile = specfile.replace(
    '@CUSTOM_PKG_DATA@', pluginrpm.get('custom_pkg_data', '')
)

# write final specfile
with open('plugin.specfile', 'w+') as wfile:
    wfile.write(specfile)
