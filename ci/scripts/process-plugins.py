#!/usr/bin/env python3

import re
import os
import json
from sys import argv
from git import Repo

common_dir = [
    'centreon-plugins/centreon/common',
    'centreon-plugins/centreon/plugins'
]

prefix = json.loads(argv[1])

# Make dictionary with all configurations of plugins
plgconfig = {}
for root, dirs, files in os.walk('./ci/packaging'):
    for file in files:
        if file == 'pkg.json':
            with open(os.path.join(root, file), 'r') as cfg:
                plgconfig[os.path.basename(root)] = json.load(cfg)


def plugins_by_directory(directory):
    # Get package name for build based on configuration
    # file (pkg.json) from a list of changed directories
    # by git
    #
    # return a array with package names
    #
    return [x['pkg_name'] for x in list(
        plgconfig.values()
    ) if ( directory in x['files'] and x['pkg_name'].startswith(tuple(prefix)) )]


repo = Repo()
commits = list(repo.iter_commits())
dir = { 'include': [] }
for i in commits[1].diff(commits[2]):
    # common_dir changed
    # if changed, all plugins are selected
    if os.path.dirname(i.a_path) in common_dir:
        for plg in list(
            [i for i in plgconfig.keys() if i.startswith(tuple(prefix))]
        ):
            dir['include'].append({'plugin-name': plg})
        break
    # return plugin name for building by directory changed
    if re.match(r'^centreon-plugins\/.*', os.path.dirname(i.a_path)):
        plgs = plugins_by_directory(
            os.path.dirname(i.a_path).replace('centreon-plugins/', '')
        )
        if len(plgs) > 0:
            for item in plgs:
                if {'plugin-name': item} not in dir['include']:
                    dir['include'].append({'plugin-name': item})
    # check ci/packging directory
    if re.match(r'^ci\/packging\/centreon-plugin-.*', os.path.dirname(i.a_path)):
        plugin_name = re.search(
            r'centreon-plugin-.*?(?=\/|$)', os.path.dirname(i.a_path)
        ).group()
        if (
            {'plugin-name': plugin_name} not in dir['include'] or
            not plugin_name.startswith(tuple(prefix))
        ):
            dir['include'].append({'plugin-name': plugin_name})

if dir['include'] == []:
    dir = []
print(json.dumps(dir))
