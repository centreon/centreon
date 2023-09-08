const newman = require('newman');
const dayjs = require('dayjs');
const fs = require('fs');

const currentDate = dayjs().format("YYYY-MM-DDTHH-mm-ss");
const fileName = `./newman-run-${currentDate}.log`;

var fd = fs.openSync(fileName, 'w');

newman.run({
    collection: require('./Collection-API-v2.json'),
    environment: require('./Environment-v2.json'),
    iterationCount: 3,
    reporters: ['htmlextra', 'text', 'cli'],
    reporter: {
        htmlextra: {
            'browserTitle':`Newman-Run-${currentDate}-Test-Results`,
            'title':`Newman-Run-${currentDate}-Test-Results`,
            'logs':'true',
            'testPaging':'true',
            'displayProgressBar':'true'
        },
        text: {
            export: `./logs/${currentDate}.log`
        }
    }
}).on('assertion', (error, data) => {
    if(error) {
        console.log(error);
        return;
    }
    console.log(data);
})
.on('request', (error, data) => {
    if(error) {
        console.log(error);
        return;
    } 
    const requestInfo = data.item.request.method.concat(" ", data.item.name.replace(/[^a-z0-9[]]/gi, '-').concat("","\r\n"));
    fs.appendFile(fileName, requestInfo, function(error) {
        if(error) {
            console.log(error);
        }
    })

    const content = data.response.stream.toString().concat("","\r\n");
    if (content) {
        fs.appendFile(fileName, content, function(error) {
            if(error) {
                console.log(error);
            }
        })
    }
});