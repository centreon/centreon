import fs from 'fs';
import path from 'path';

const jtlFilePath = process.argv[2];
const outputPath = process.argv[3];

fs.readFile(jtlFilePath, 'utf8', (err, data) => {
    if (err) throw err;


    const rows = data.split('\n').map(row => row.split(','));


    let html = `
        <h2>JMeter Test Results</h2>
        <table style="width:100%; border-collapse: collapse; border: 1px solid #ddd; text-align: left;">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Elapsed</th>
                    <th>Label</th>
                    <th>Response Code</th>
                    <th>Response Message</th>
                    <th>Thread Name</th>
                    <th>Success</th>
                    <th>Bytes</th>
                    <th>Latency</th>
                    <th>URL</th>
                </tr>
            </thead>
            <tbody>`;

    rows.forEach((row, index) => {
        if (index === 0) return;

        html += `
            <tr>
                <td>${row[0]}</td>
                <td>${row[1]}</td>
                <td>${row[2]}</td>
                <td>${row[3]}</td>
                <td>${row[4]}</td>
                <td>${row[5]}</td>
                <td>${row[6]}</td>
                <td>${row[8]}</td>
                <td>${row[14]}</td>
                <td><a href="${row[13]}" target="_blank">Link</a></td>
            </tr>`;
    });

    html += `</tbody></table>`;

    fs.writeFile(outputPath, html, (err) => {
        if (err) throw err;
        console.log('HTML report generated!');
    });
});
