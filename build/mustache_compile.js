var Hogan = require('./hogan'),
    fs = require('fs');

function compileTemplate (template, callback) {
    var filename = __dirname + '/../../' + template;

    fs.readFile(filename, function(err, contents) {
        if (err) {
            throw err;
        } else {
            var func = Hogan.compile(contents.toString(), {asString: true});
            callback(func);
        }
    });

}

if (process.argv.length < 3) {
    console.log("Usage: \n\techo file.js | node octopus/build/mustache_compile viewdir");
    return;
}

var templateDir = process.argv[2],
    templates = fs.readdirSync(templateDir),
    output = [],
    total = templates.length,
    done = 0;

function finished() {
    done++;
    if (done === total) {
        console.log(output.join(",\n"));
    }

}

templates.forEach(function(i, c) {
    if (!i.match(/\.mustache$/)) {
        finished();
        return;
    }
    compileTemplate(templateDir + '/' + i, function(js) {
        output.push(i.replace(/\.mustache$/, '') + ': new Hogan.Template(' + js + ')');
        finished();
    });
})

