const fs = require('fs');
const path = require('path');

const sourcesDir = './sources';
const files = fs.readdirSync(sourcesDir)
  .filter(file => file.endsWith('.txt'))
  .map(file => `sources/${file}`);

fs.writeFileSync('sources.json', JSON.stringify(files));
console.log(`Wygenerowano sources.json z ${files.length} plikami:`);
console.log(files);
