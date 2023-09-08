const fs = require('fs');
const path = require('path');
const newman = require('newman');
const dayjs = require('dayjs');

const currentDate = dayjs().format("YYYY-MM-DDTHH-mm-ss");
const logDir = './logs';
const newmanDir = './newman';

// Créez les répertoires de logs et de newman s'ils n'existent pas déjà
if (!fs.existsSync(logDir)) {
  fs.mkdirSync(logDir);
}

if (!fs.existsSync(newmanDir)) {
  fs.mkdirSync(newmanDir);
}

// Récupérez la liste des fichiers de collection dans le dossier
const collectionDir = 'centreon/tests/rest_api/refactored_api_test_collection';
const collectionFiles = fs.readdirSync(collectionDir);

// Parcourez chaque fichier de collection et exécutez Newman
collectionFiles.forEach((collectionFile) => {
  const collectionFilePath = path.join(collectionDir, collectionFile);
  const collectionName = path.basename(collectionFile, '.postman_collection.json');
  
  const logFileName = `newman-run-${collectionName}-${currentDate}.log`;
  const logFilePath = path.join(logDir, logFileName);

  newman.run({
    collection: require(collectionFilePath),
    environment: require('./Environment-for-independent-test-scenarios-collection.postman_environment.json'),
    iterationCount: 3,
    reporters: ['htmlextra', 'text', 'cli'],
    reporter: {
      htmlextra: {
        browserTitle: `Newman-Run-${collectionName}-${currentDate}-Test-Results`,
        title: `Newman-Run-${collectionName}-${currentDate}-Test-Results`,
        logs: 'true',
        testPaging: 'true',
        displayProgressBar: 'true',
      },
      text: {
        export: path.join(logDir, `${collectionName}-${currentDate}.log`),
      },
    },
  }).on('assertion', (error, data) => {
    if (error) {
      console.log(error);
      return;
    }
    console.log(data);
  }).on('request', (error, data) => {
    if (error) {
      console.log(error);
      return;
    }

    const requestInfo = `${data.item.request.method} ${data.item.name.replace(/[^a-z0-9[]]/gi, '-')}\r\n`;
    fs.appendFileSync(logFilePath, requestInfo);

    const content = data.response.stream.toString() + '\r\n';
    if (content) {
      fs.appendFileSync(logFilePath, content);
    }
  });
});
