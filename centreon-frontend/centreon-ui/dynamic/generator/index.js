#!/usr/bin/env node

var program = require("commander");

program
  .version("0.0.1")
  .option(
    "-t, --topology [name]",
    "Component topology name, if not defined, capitalized functional name will be used"
  )
  .option("-n, --fname [name]", "Component functional name ( required )")
  .parse(process.argv);

var clc = require("cli-color");
var fs = require("fs-extra");
var replaceInFiles = require("replace-in-files");

if (program.fname) {
  fs.copy(__dirname + "/template", process.cwd() + "/template", function(err) {
    if (err) {
      console.log(clc.red.bgBlack("An error occured while copying template."));
      return console.error(err);
    }
    console.log(clc.green.bgBlack("Component template initialized."));
    console.log(clc.blueBright.bgBlack("Running generation..."));
    replaceNames();
  });
} else {
  console.log(clc.red.bgBlack("Component name is required!"));
  console.log(
    clc.blueBright.bgBlack("Run centreon-compgen --help for more info.")
  );
}

async function replaceNames() {
  var replaceOptions = {
    files: `${process.cwd()}/template/**/*`,

    from: /COMPONENT_FUNCTIONAL_NAME/g,
    to: `${program.fname}`,

    saveOldFile: false,
    encoding: "utf8",
    onlyFindPathsWithoutReplace: false,
    returnPaths: true,
    returnCountOfMatchesByPaths: true
  };

  try {
    var from = /COMPONENT_CAPITALIZED_NAME/g;
    var to = "";
    if (program.topology) {
      to = program.topology;
    } else {
      to = program.fname.charAt(0).toUpperCase() + program.fname.slice(1);
    }

    const {
      changedFiles,
      countOfMatchesByPaths,
      replaceInFilesOptions
    } = await replaceInFiles(replaceOptions).pipe({ from: from, to: to });
    fs.renameSync(
      `${process.cwd()}/template/`,
      `${process.cwd()}/${program.fname}Compiler/`
    );
    fs.renameSync(
      `${process.cwd()}/${program.fname}Compiler/src/componentName`,
      `${process.cwd()}/${program.fname}Compiler/src/${program.fname}`
    );
    console.log(
      clc.green.bgBlack(
        `${program.topology} external component compiler generated.`
      )
    );
    console.log(
      clc.white.bgBlack(
        `To use, replace COMPONENT_BUILD_PATH in package.json with the desired output folder.`
      )
    );
    console.log(
      clc.white.bgBlack(
        `To run compiler, run "npm run install" and you can use "npm run compile"`
      )
    );
  } catch (error) {
    console.log("Error occurred:", error);
  }
}
