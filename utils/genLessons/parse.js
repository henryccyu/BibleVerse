let fs = require('fs');
let fsSync = require('fs-sync');
const books = require('./books.json');
let textract = require('textract');

let bookArray = []
Object.keys(books).forEach(item => {
  bookArray.push(item);
});

let Year = 2018;
let Lesson = 1;
let Index = 1;

let DayQuestionSpliter = [
  'Scripture Memory Verse', 'Versículo de las Escrituras para memorizar',
  'FIRST DAY:', 'PRIMER DÍA:',
  'SECOND DAY:', 'SEGUNDO DÍA:',
  'THIRD DAY:', 'TERCER DÍA:',
  'FOURTH DAY:', 'CUARTO DÍA:',
  'FIFTH DAY:', 'QUINTO DÍA:',
  'SIXTH DAY:', 'SEXTO DÍA:'];

function getNextString(spliters, content) {
  let location = 99999;
  let spliter;

  spliters.forEach(item => {
    let index = content.indexOf(item);
    if (index !== -1 && index < location) {
      spliter = item;
      location = index;
    }
  });

  if (location != 99999) {
    return {
      before: content.substring(0, location),
      spliter,
      after: content.substring(location + spliter.length)
    };
  }

  return {};
}

function split(spliters, content) {
  let result = [];

  let value = getNextString(spliters, content);
  while (value.spliter) {
    if (value.before.length > 0) {
      result.push(value.before);
    }
    result.push(value.spliter);

    content = value.after;
    value = getNextString(spliters, content);
  }

  if (content.length > 0) {
    result.push(content);
  }

  return result;
}

function parseLessonId(content) {
  var strs = split(['Lesson Review', 'Lesson', 'Lección ', '\n'], content);
  let id;
  strs.some(item => {
    if (['Lesson Review'].indexOf(item) !== -1) {
      Lesson = 30;
      id = Year + '_' + Lesson;
      return true;
    }

    const value = parseInt(item);
    if (value > 0) {
      Lesson = value;
      id = Year + '_' + Lesson;
      return true;
    }
  });

  console.assert(id, 'Cannot find lesson Id');
  return id;
}

function getVerseString(verse) {
  let result = '';
  for (let i = 0; i < verse.length; i++) {
    const char = verse[i];
    if ('0123456789:-'.indexOf(char) !== -1) {
      result += char;
    }
  }

  return result;
}

function parseVerses(content) {
  let result = [];
  let currentChapter = -1;
  let words = content.split(' ');
  for (let i = 0; i < words.length; i++) {
    let verse = words[i].trim();

    // skip some words
    if (verse.indexOf('and') !== -1) {
      continue;
    }

    if (/^[a-zA-Z]*$/.test(verse)) {
      break;
    }

    verse = getVerseString(verse);
    if (verse.length === 0) {
      continue;
    }

    if (/^[\d]+$/.test(verse)) {
      // (number)
      if (currentChapter === -1) {
        // whole chapter
        verse += ':1-999';
      } else {
        // (currentChapter):(verse)
        verse = `${currentChapter}:${verse}`;
      }
    } else if (/^[\d]+\-[\d]+$/.test(verse)) {
      // (number)-(number)
      let range = verse.split('-');
      if (currentChapter === -1) {
        // multiple chapters
        verse = `${range[0]}:1-${range[1]}:999`;
      } else {
        // multiple verses
        verse = `${currentChapter}:${range[0]}-${range[1]}`;
      }
    } else if (/^\d+:\d+\-\d+:\d+$/.test(verse)) {
      // (number):(number)-(number):(number)
      const strs = verse.split(/[\:\-]/);
      currentChapter = parseInt(strs[2]);
    } else if (/^\d+:\d+$/.test(verse)) {
      // (number):(number)
      const strs = verse.split(':');
      currentChapter = parseInt(strs[0]);
    }

    result.push(verse);
  }

  if (result.length === 0) {
    return null;
  }

  return result;
}

function parseReadVerse(content) {
  let result = [];
  const strs = split(bookArray, content);// content.split(' ');
  for (let i = 0; i < strs.length; i++) {
    const item = strs[i];
    if (books[item]) {
      let verses = parseVerses(strs[++i].trim());
      if (verses) {
        console.log(JSON.stringify({ book: item, verses }) + ' ' + content);
        verses.forEach(verse => {
          result.push({
            book: item,
            verse
          });
        });
      }
    }
  }

  return result;
}

function parseQuotes(content) {
  return parseReadVerse(content);
}

function parseQuestions(indexString, content) {
  let currentIndex = parseInt(indexString.substring(0, indexString.indexOf('.')));
  console.assert(currentIndex >= Index);
  Index = currentIndex;

  // merge questions
  const strs = content.split('\n');
  let currentQuestion = '';
  let questions = [];
  for (let i = 0; i < strs.length; i++) {
    const line = strs[i].trim();
    if (line.length === 0) {
      continue;
    }

    if (line[1] == '.' && line[2] == ' ') {
      console.assert('abcdefghijklmn'.indexOf(line[0]) != -1);
      if (currentQuestion.length > 0) {
        questions.push(currentQuestion);
      }

      currentQuestion = line.substring(0, 3) + line.substring(3).trim();
    } else {
      currentQuestion += ' ' + line;
    }
  }

  if (currentQuestion.length > 0) {
    questions.push(currentQuestion);
  }

  console.assert(questions.length > 0);
  let result = [];
  questions.forEach(item => {
    const questionText = Index + '. ' + item.replace(/\n/g, ' ').trim();
    const id = `${Year}_${Lesson}_${Index}${questions.length === 1 ? '' : item[0]}`;
    result.push({
      id,
      questionText,
      answer: '',
      quotes: parseQuotes(questionText)
    });
  });

  return result;
}

function parseDayQuestion(content) {
  let questionIndexSpliter = [];
  for (let i = Index; i < 20; i++) {
    questionIndexSpliter.push('\n' + i + '. ');
  }

  let strs = split(questionIndexSpliter, content);
  console.assert(strs.length % 2 === 1);
  const title = strs[0].trim();
  let result = { title };
  const titleFirstLine = strs[0].split('\n')[0];
  let readVerse = parseReadVerse(titleFirstLine);
  if (readVerse.length > 0) {
    result.readVerse = readVerse;
  }
  result.questions = [];

  for (let i = 1; i < strs.length; i += 2) {
    const questions = parseQuestions(strs[i].trim(), strs[i + 1].trim());
    questions.forEach(item => {
      result.questions.push(item);
    });
  }

  return result;
}

function parseHomework(content) {
  let strs = split(DayQuestionSpliter, content);
  let pos = 3;
  const id = parseLessonId(strs[0]);
  console.assert(strs.length === 15);
  return {
    id,
    name: "",
    memoryVerse: strs[2].replace(/\n/g, ' ').trim(),
    dayQuestions: {
      one: parseDayQuestion(strs[pos++] + strs[pos++]),
      two: parseDayQuestion(strs[pos++] + strs[pos++]),
      three: parseDayQuestion(strs[pos++] + strs[pos++]),
      four: parseDayQuestion(strs[pos++] + strs[pos++]),
      five: parseDayQuestion(strs[pos++] + strs[pos++]),
      six: parseDayQuestion(strs[pos++] + strs[pos++])
    }
  }
}

function parse(dir, file) {
  Year = 2018;
  Lesson = 1;
  Index = 1;

  const input = `${dir}\\${file}`;
  console.log(`Reading [${input}]...`);
  let content = fsSync.read(input);

  // formatting
  content = content.replace(/\t/g, ' ').replace(/\r/g, '');

  let newContent = '';
  let removeMode = false;
  content.split('\n').forEach(line => {
    if (removeMode) {
      let index = line.indexOf(')');
      if (index !== -1) {
        line = line.substring(index + 1);
        removeMode = false;
      }
    }

    let index = line.indexOf('Copyright © Bible Study Fellowship 2018');
    if (index !== -1) {
      // not ending, we will enter remove mode
      removeMode = line.indexOf(')', index) === -1;

      line = line.substring(0, index);
    }

    line = line.trim();
    if (line.length > 0) {
      newContent += line + '\n';
    }
  });

  const result = parseHomework(newContent);
  const output = `${dir}\\${Year}_${Lesson}.json`;
  console.log(`Writing to[${output}]...\n`);
  fsSync.write(output, JSON.stringify(result));
}

function main() {
  dir = 'eng';
  fs.readdir(dir, (err, files) => {
    files.forEach(file => {
      if (!file.endsWith('.docx')) {
        return;
      }

      textract.fromFileWithPath(dir + '\\' + file, { preserveLineBreaks: true }, function (error, text) {
        fsSync.write(dir + '\\' + file + '.txt', text);
        parse(dir, file + '.txt');
      });
    });
  })
}


// Main
//parse('eng', 'PPL1_AdQ_30_062018v1.docx.txt');
main();