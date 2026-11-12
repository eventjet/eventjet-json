// Writes tests/Unit/Schema/ecma-regexes.json: regexes with the verdicts of a real ECMA-262 engine (V8, through
// Node.js), for EcmaRegexTest. For each regex, Node decides whether it is valid with the `u` flag and, if it is, what
// it matches in a few subjects. The regexes come from a fixed list and from a seeded generator that follows the
// grammar of ECMA-262 (11th edition, which JSON Schema 2020-12 cites), so the file only changes with this script.
//
// Run with: node .github/ci/ecma-regexes.mjs

import { writeFileSync } from 'node:fs';

const OUTPUT = new URL('../../tests/Unit/Schema/ecma-regexes.json', import.meta.url);
const GENERATED = 1500;
const SUBJECTS = 8;

// Characters whose Unicode properties are the same in every version that PCRE and Node might use.
const PALETTE = [
    'a', 'b', 'c', 'x', 'A', 'Z', '0', '1', '9', '_', '-', ' ', ',', '<', '>', '=', '!', '/', ':',
    '\t', '\n', '\v', '\f', '\r', '\0', '\x01', '\x1a',
    '\u00a0', '\u00e9', '\u00df', '\u0130', '\u017f', '\u01c5', '\u03a9', '\u03c9', '\u0416', '\u05d0', '\u0663',
    '\u0e01', '\u1680', '\u2000', '\u2028', '\u2029', '\u202f', '\u205f', '\u2160', '\u3000', '\u4e2d', '\ufeff',
    '\ud7ff', '\ue000', '\uffff', '\u{10000}', '\u{1d400}', '\u{1f600}', '\u{10ffff}',
];
const LITERALS = PALETTE.filter((c) => !'^$\\.*+?()[]{}|/'.includes(c) && c !== '-' && c.codePointAt(0) > 0x1f);
const PROPERTIES = [
    'L', 'Letter', 'Lu', 'Ll', 'Lt', 'LC', 'Lm', 'Lo', 'M', 'Mn', 'N', 'Nd', 'Nl', 'P', 'Pd', 'S', 'Z', 'Zs', 'Zl',
    'C', 'Cc', 'Cf', 'Co', 'Cs', 'Any', 'ASCII', 'Assigned', 'Alphabetic', 'White_Space', 'Uppercase', 'ID_Start',
    'gc=Lu', 'General_Category=Letter', 'gc=Decimal_Number', 'Script=Latin', 'sc=Greek', 'sc=Cyrillic',
    'Script=Hebrew', 'sc=Thai', 'sc=Han', 'Script=Common', 'sc=Zinh', 'scx=Latin', 'Script_Extensions=Greek',
    'scx=Hani',
    // Invalid names.
    'Foo', 'letter', 'L&', 'Script', 'Script=', 'sc=Foo', 'gc=Latin', 'sc=Lu', 'Lowercase_Letter=Lu', 'ASCII=Yes',
    'Block=Basic_Latin', 'In_Greek', 'IsGreek', 'Script=Latn ', '',
];
const NAMES = ['a', 'b', 'x1', '$', '_n', '\u00e9', '\u03a9'];

// Regexes with subjects that random ones would rarely find: back references to groups in quantified atoms, and
// characters whose Script differs from their Script_Extensions.
const WITH_SUBJECTS = [
    ['^(?:(a)|b\\1)+$', ['ab', 'aba', 'abb', 'b']],
    ['^(?:(a)|b)+\\1$', ['ab', 'aba', 'aa', 'aaa', 'ba']],
    ['^(?:(a)|(b))+\\1\\2$', ['ab', 'abb', 'aba', 'ba', 'baa']],
    ['^(?:(?:(a)|b)c)*\\1$', ['ac', 'aca', 'acbc', 'acbca']],
    ['^(?:(a)?b)+\\1$', ['abb', 'abba', 'ab', 'aba']],
    ['^(?:((a)|b)(c))+\\2$', ['acbc', 'acbca', 'ac', 'aca']],
    ['^(?:(?<n>a)|b)+\\k<n>$', ['ab', 'aba']],
    ['^(?:(a)|b){2}\\1$', ['ab', 'aba', 'aaa']],
    ['^(?:(a)|b){1,2}?\\1$', ['ab', 'aba', 'aa']],
    ['^(?:(a)|b)?\\1$', ['', 'aa', 'b']],
    ['^\\p{Script=Thaana}$', ['\u0663', '\u0780']],
    ['^\\p{sc=Arabic}$', ['\u0663', '\u0780']],
    ['^\\p{scx=Thaana}$', ['\u0663', '\u0780']],
];

// Syntax that the generator rarely produces on its own, both valid and invalid.
const FIXED = [
    '', 'a', '^$', '$^', '\\', 'a\\', '(', ')', '[', ']', '{', '}', 'a{', 'a{1', 'a{1,', 'a{,1}', 'a{1}{2}',
    'a{2,1}', 'a{1,1}', 'a{0}', 'a{0,0}', 'a{01}', 'a{1,02}', 'a{99999999999999999999}', 'a{1,99999999999999999999}',
    'a{65535}', 'a{65536}', 'a{0,65536}', '*', '+', '?', 'a**', 'a*?', 'a*??', 'a+*', '|', 'a||b', '(|)', '()',
    '(?:)', '(?)', '(?a)', '(?i:a)', '(?<)', '(?<>a)', '(?<a)', '(?<a>)', '(?<a>a)(?<a>b)', '(?<a>a)|(?<a>b)',
    '(?<1a>a)', '(?<a-b>a)', '(?<\\u0061>a)', '(?<\\u{61}>a)', '(?<a\\u0062>a)\\k<ab>', '\\k', '\\k<a>',
    '(?<a>x)\\k', '(?<a>x)\\k<a', '(?<a>x)\\k<b>', '\\k<a>(?<a>x)', '(?<a>x)\\k<a>', '(a)\\1', '\\1(a)', '(a\\1)',
    '(a)\\2', '\\1', '\\0', '\\00', '\\01', '\\08', '\\0a', '\\8', '\\9', '(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)\\10',
    '(a)\\10', '\\a', '\\e', '\\z', '\\-', '\\ ', '\\"', '\\/', '/', '\\]', '\\}', '\\|', '\\_', '\\é',
    '\\c', '\\cA', '\\cz', '\\c1', '\\c_', '[\\c]', '[\\c1]', '[\\c_]', '\\x', '\\x4', '\\x41', '\\x4g', '\\xG1',
    '\\u', '\\u004', '\\u0041', '\\u{', '\\u{}', '\\u{41}', '\\u{0041}', '\\u{000000041}', '\\u{10FFFF}',
    '\\u{110000}', '\\u{FFFFFFFFFFFFFFFFFFFF}', '\\u{41', '\\u{g}', '\\uD83D\\uDE00', '\\uD83D', '\\uDE00',
    '\\uDE00\\uD83D', '\\uD83D\\u0041', '\\uD83D\\uDBFF', '\\uDBFF\\uDFFF', '\\uD800\\uDC00', '\\uD83D\\u{DE00}',
    '\\u{D83D}\\uDE00', '\\uD83D\\uDE0', '\\uD83D\\', '\\uD83D\\u', '[\\uD83D\\uDE00]', '[\\uD83D-\\uDE00]',
    '[\\uD800-\\uDFFF]', '[^\\uD800-\\uDFFF]', '.', '[]', '[^]', '[\\]]', '[]]', '[a-]', '[-a]', '[a-z]', '[z-a]',
    '[a-a]', '[\\d-z]', '[a-\\d]', '[\\d-\\w]', '[--a]', '[a--]', '[a-z-0]', '[\\b]', '\\b', '\\B', '[\\B]',
    '[\\-]', '[\\^]', '[^^]', '[\\[]', '[[]', '[\\p{L}]', '[^\\P{L}]', '[\\p{L}-z]', '\\p', '\\p{', '\\p{L',
    '\\pL', '\\P', '[\\p]', '\\p{}', '\\p{L}', '\\P{L}', '[\\s\\S]', '[^\\s\\S]', '[\\0]', '[\\00]', '[\\01]',
    '[\\8]', '[\\1]', '(a)[\\1]', '[\\k<a>]', '(?<a>x)[\\k<a>]', '[\\x]', '[\\u{110000}]', '(?=a)', '(?!a)',
    '(?<=a)', '(?<!a)', '(?=a)*', '(?=a)?', '(?!a){2}', '(?<=a)*', '(?<!a)+', '^*', '$+', '\\b*', '\\B?', '(?=',
    '(?<=a', '(?<=a|bc)d', '(?<!a|bc)d', '(?<=a{1,3})b', '(?<=(a))b\\1', '(?<=\\b)a', '(?<=^)a', '(?<=a$)',
    '(?=(a))\\1', '(?!(a))\\1', '(?:(a)|b)+', '(?:(a)|b\\1)+', '(?:(a)|b)\\1', '(a)|\\1b', '(a*)*', '(a*)+b',
    '(?:a|ab)(?:c|bcd)', '(a|ab)(c|bcd)(d*)', '(.*?)a', '(?:a?)*?b', 'a.c', '^.$', '\\s', '\\S', '\\d', '\\D',
    '\\w', '\\W', '[\\w-]', '[\\w-a]', '[a-\\w]', '\\ud83d\\ude00', '[😀-😁]', '[😁-😀]', '😀*', '^😀{2}$', 'é+',
    '(?<\\u00e9>a)\\k<\u00e9>', '(?<\\u4e2d>a)\\k<\u4e2d>', '(?<\\u{1d400}>a)\\k<\u{1d400}>',
    '(?<\\ud835\\udc00>a)\\k<\u{1d400}>',
    '(?<\\u{7f}>a)', '(?<\\u{80}>a)', '(?<\\u{7ff}>a)', '(?<\\u{800}>a)', '(?<\\u{ffff}>a)', '(?<\\u{10000}>a)',
    '(?<\\u{e9}\\u{e9}>a)\\k<\u00e9\u00e9>',
    '(?<a\\uD835>a)', '(?<a\\x41>a)', '(?<a\\>a)', '(?<a\\u>a)', '(?<a\\u{110000}>a)', '(?<$\\u{24}>a)\\k<$$>',
    '(?<_>a)\\k<\\u005f>',
    '(?<a>a)+\\k<a>', '(?<a>a)?\\k<a>', '(?:(?<a>a)b)*\\k<a>', '(a){2}\\1', '(a){1}\\1', '(a){0,1}\\1', '(a){1,}\\1',
    '(a)*?\\1',
    '(?:(a))?\\1', '(?:(?:(a))?)+\\1', '(?:(a)|b)+(c)\\2', '((a)|b)\\2', '(?=(a))+', '(?:\\1(a))+', '[(](a)+\\1',
    '\\((a))+\\1',
    '(?<au>x)\\k<au>', '(?<a\\u>x)', '(?<a\\x41>x)',
    '(?<a>x)\\kxa>', '\\pXL}', '[\\pXL}]', '\\uD800', '\\uDFFF', '[\\uD800]', '[\\uDFFF]', '(?<a\n>x)', '[\\', 'a[\\',
    '\\p{Script=Latin}+', '\\p{Lu}\\p{Ll}*', '\\P{Any}', '\\p{Any}', '\\p{Assigned}', '\\P{Assigned}',
    '\\p{ASCII}', '\\p{L}\\P{L}', '[\\p{Nd}a]', '\\p{Script_Extensions=Latin}', '\\p{scx=Hani}', '\\p{sc=Hani}',
];

const random = mulberry32(20261109);

function mulberry32(seed) {
    return () => {
        seed = (seed + 0x6d2b79f5) | 0;
        let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function int(max) {
    return Math.floor(random() * max);
}

function pick(items) {
    return items[int(items.length)];
}

function chance(probability) {
    return random() < probability;
}

function hex(codePoint, digits) {
    return codePoint.toString(16).toUpperCase().padStart(digits, '0');
}

/** Builds a random regex from the grammar, tracking groups so that references can name them. */
class Generator {
    groups = 0;
    names = [];

    pattern() {
        return this.disjunction(0, false);
    }

    disjunction(depth, behind) {
        const alternatives = [this.alternative(depth, behind)];
        while (chance(0.2)) {
            alternatives.push(this.alternative(depth, behind));
        }
        return alternatives.join('|');
    }

    alternative(depth, behind) {
        let out = '';
        const terms = int(depth === 0 ? 5 : 3);
        for (let i = 0; i < terms; i++) {
            out += this.term(depth, behind);
        }
        return out;
    }

    term(depth, behind) {
        if (chance(0.12)) {
            return this.assertion(depth, behind);
        }
        return this.atom(depth, behind) + this.quantifier(behind);
    }

    assertion(depth, behind) {
        if (depth >= 2 || chance(0.5)) {
            return pick(['^', '$', '\\b', '\\B']);
        }
        const kind = pick(['?=', '?!', '?<=', '?<!']);
        const lookbehind = behind || kind.startsWith('?<');
        return '(' + kind + this.disjunction(depth + 1, lookbehind) + ')';
    }

    quantifier(behind) {
        if (chance(0.65)) {
            return '';
        }
        // PCRE needs lookbehinds of bounded length.
        const greedy = behind
            ? pick(['?', '{2}', '{0,2}', '{1,3}'])
            : pick(['*', '+', '?', '{2}', '{0}', '{1,}', '{0,2}', '{2,3}']);
        return greedy + (chance(0.3) ? '?' : '');
    }

    atom(depth, behind) {
        const roll = random();
        if (roll < 0.4) {
            return pick(LITERALS);
        }
        if (roll < 0.47) {
            return '.';
        }
        if (roll < 0.65) {
            return '\\' + this.atomEscape(behind);
        }
        if (roll < 0.8) {
            return this.characterClass();
        }
        if (depth >= 3) {
            return pick(LITERALS);
        }
        const kind = random();
        if (kind < 0.4) {
            return '(?:' + this.disjunction(depth + 1, behind) + ')';
        }
        if (kind < 0.75 || behind) {
            this.groups++;
            return '(' + this.disjunction(depth + 1, behind) + ')';
        }
        const name = pick(NAMES.filter((n) => !this.names.includes(n)).concat(['a']));
        this.names.push(name);
        this.groups++;
        return '(?<' + name + '>' + this.disjunction(depth + 1, behind) + ')';
    }

    atomEscape(behind) {
        const roll = random();
        // Back references inside lookbehinds match backwards in ECMA-262, which PCRE can't do.
        if (roll < 0.1 && !behind) {
            return String(1 + int(this.groups + 2));
        }
        if (roll < 0.15 && !behind) {
            return 'k<' + pick(NAMES.slice(0, 3)) + '>';
        }
        if (roll < 0.45) {
            return this.classEscape();
        }
        return this.characterEscape();
    }

    classEscape() {
        if (chance(0.6)) {
            return pick(['d', 'D', 's', 'S', 'w', 'W']);
        }
        return pick(['p', 'P']) + '{' + pick(PROPERTIES) + '}';
    }

    characterEscape() {
        const codePoint = pick(PALETTE).codePointAt(0);
        switch (int(9)) {
            case 0:
                return pick(['t', 'n', 'v', 'f', 'r', '0']);
            case 1:
                return 'c' + pick(['A', 'J', 'z', 'm']);
            case 2:
                return codePoint < 0x100 ? 'x' + hex(codePoint, 2) : 'x41';
            case 3:
                return codePoint < 0x10000 ? 'u' + hex(codePoint, 4) : 'u{' + hex(codePoint, 1) + '}';
            case 4: {
                const s = String.fromCodePoint(codePoint);
                return s.length === 2 ? 'u' + hex(s.charCodeAt(0), 4) + '\\u' + hex(s.charCodeAt(1), 4) : 'u0041';
            }
            case 5:
                return 'u{' + hex(codePoint, int(3) + 1) + '}';
            case 6:
                return pick(['uD83D', 'uDE00', 'uDBFF\\uDFFF', 'uD800\\uDC00']);
            default:
                return pick(['^', '$', '\\', '.', '*', '+', '?', '(', ')', '[', ']', '{', '}', '|', '/']);
        }
    }

    characterClass() {
        let out = chance(0.3) ? '[^' : '[';
        const items = int(4);
        for (let i = 0; i < items; i++) {
            if (chance(0.35)) {
                const [from, to] = [this.classAtom(), this.classAtom()];
                out += from + '-' + to;
            } else {
                out += this.classAtom();
            }
        }
        return out + ']';
    }

    classAtom() {
        const roll = random();
        if (roll < 0.5) {
            return pick(LITERALS.concat(['-', '^', '$', '.', '*', '(', ')', '{', '}', '|', '/']));
        }
        if (roll < 0.6) {
            return pick(['\\b', '\\-']);
        }
        if (roll < 0.75) {
            return '\\' + this.classEscape();
        }
        return '\\' + this.characterEscape();
    }
}

/** Makes a small random edit that may make a valid regex invalid. */
function mangle(regex) {
    const chars = [...regex];
    const at = int(chars.length + 1);
    const syntax = pick(['(', ')', '[', ']', '{', '}', '\\', '|', '?', '*', '+', '^', '$', '<', '>', '-', ',', '1']);
    switch (int(3)) {
        case 0:
            chars.splice(at, 0, syntax);
            break;
        case 1:
            chars.splice(at, 1);
            break;
        default:
            chars.splice(at, 1, syntax);
    }
    return chars.join('');
}

function compile(regex) {
    let compiled;
    try {
        compiled = new RegExp(regex, 'u');
    } catch {
        return null;
    }
    return newerSyntax(regex) ? null : compiled;
}

/**
 * Whether the regex uses syntax that ECMA-262 added after its 11th edition, which V8 accepts: modifiers such as
 * `(?i:…)`, and capturing groups that share a name.
 */
function newerSyntax(regex) {
    const chars = [...regex];
    const names = [];
    let inClass = false;
    for (let i = 0; i < chars.length; i++) {
        if (chars[i] === '\\') {
            i++;
        } else if (inClass) {
            inClass = chars[i] !== ']';
        } else if (chars[i] === '[') {
            inClass = true;
        } else if (chars[i] === '(' && chars[i + 1] === '?') {
            if (/[imsx-]/.test(chars[i + 2] ?? '')) {
                return true;
            }
            const name = /^<([^=!>][^>]*)>/.exec(chars.slice(i + 2).join(''));
            if (name !== null) {
                names.push(name[1]);
            }
        }
    }
    return new Set(names).size !== names.length;
}

/** The characters that the regex mentions literally, so that subjects contain them more often. */
function mentioned(regex) {
    return [...regex].filter((c) => !'^$\\.*+?()[]{}|/'.includes(c));
}

function subjects(compiled, regex, fixed) {
    const literals = mentioned(regex);
    const matching = [];
    const failing = [];
    for (const subject of fixed) {
        (compiled.test(subject) ? matching : failing).push([subject, compiled.test(subject)]);
    }
    for (let attempt = 0; attempt < 100 && matching.length + failing.length < SUBJECTS; attempt++) {
        let subject = '';
        const length = int(6);
        for (let i = 0; i < length; i++) {
            subject += literals.length > 0 && chance(0.6) ? pick(literals) : pick(PALETTE);
        }
        const matches = compiled.test(subject);
        const target = matches ? matching : failing;
        if (target.length < SUBJECTS / 2 && ![...matching, ...failing].some(([s]) => s === subject)) {
            target.push([subject, matches]);
        }
    }
    return [...matching, ...failing];
}

/**
 * Whether PCRE can't match the valid regex the same way, so that the validator rejects it: a quantifier bound beyond
 * PCRE's limit of 65535, or a back reference to a group inside a quantified atom that can repeat. ECMA-262 forgets the
 * captures of such a group at the start of each repetition, but PCRE keeps them.
 */
function unsupported(regex) {
    const chars = [...regex];
    const repeated = new Set();
    const names = new Map();
    const references = [];
    const open = [];
    let groups = 0;
    let tooBig = false;
    let i = 0;
    const quantifier = (groupsBefore) => {
        let max = 0;
        const braces = /^\{(\d+)(,(\d*))?\}/.exec(chars.slice(i).join(''));
        if (chars[i] === '*' || chars[i] === '+') {
            max = Infinity;
            i++;
        } else if (chars[i] === '?') {
            max = 1;
            i++;
        } else if (braces !== null) {
            max = braces[2] === undefined ? Number(braces[1]) : braces[3] === '' ? Infinity : Number(braces[3]);
            tooBig ||= Number(braces[1]) > 65535 || (max > 65535 && max !== Infinity);
            i += braces[0].length;
        }
        if (chars[i] === '?' && max > 0) {
            i++;
        }
        for (let group = groupsBefore + 1; max > 1 && group <= groups; group++) {
            repeated.add(group);
        }
    };
    const name = () => {
        const end = chars.indexOf('>', i);
        const raw = chars.slice(i, end).join('');
        i = end + 1;
        return raw.replace(/\\u\{([0-9a-fA-F]+)\}|\\u([0-9a-fA-F]{4})/g, (_, braced, four) =>
            braced === undefined ? String.fromCharCode(parseInt(four, 16)) : String.fromCodePoint(parseInt(braced, 16)),
        );
    };
    while (i < chars.length) {
        const groupsBefore = groups;
        const char = chars[i];
        if (char === '(') {
            const opening = chars.slice(i + 1, i + 4).join('');
            const kind = ['?=', '?!', '?<=', '?<!', '?:'].find((k) => opening.startsWith(k));
            if (kind !== undefined) {
                i += 1 + kind.length;
            } else if (chars[i + 1] === '?') {
                i += 3;
                names.set(name(), ++groups);
            } else {
                i++;
                groups++;
            }
            open.push(groupsBefore);
            continue;
        }
        if (char === ')') {
            i++;
            quantifier(open.pop());
            continue;
        }
        if (char === '[') {
            for (i++; chars[i] !== ']'; i++) {
                i += chars[i] === '\\' ? 1 : 0;
            }
        } else if (char === '\\' && /[1-9]/.test(chars[i + 1])) {
            const digits = /^\d+/.exec(chars.slice(i + 1).join(''))[0];
            references.push(Number(digits));
            i += digits.length;
        } else if (char === '\\' && chars[i + 1] === 'k') {
            i += 3;
            references.push(name());
            i--;
        } else if (char === '\\' && 'pPu'.includes(chars[i + 1]) && chars[i + 2] === '{') {
            i = chars.indexOf('}', i);
        } else if (char === '\\') {
            i++;
        }
        i++;
        quantifier(groupsBefore);
    }
    return tooBig || references.some((reference) => repeated.has(names.get(reference) ?? reference));
}

const cases = new Map();

function add(regex, fixed = []) {
    if (cases.has(regex)) {
        return;
    }
    const compiled = compile(regex);
    if (compiled === null) {
        cases.set(regex, false);
    } else {
        cases.set(regex, unsupported(regex) ? 'unsupported' : subjects(compiled, regex, fixed));
    }
}

for (const [regex, fixed] of WITH_SUBJECTS) {
    add(regex, fixed);
}
for (const regex of FIXED) {
    add(regex);
}
while (cases.size < WITH_SUBJECTS.length + FIXED.length + GENERATED) {
    const regex = new Generator().pattern();
    add(chance(0.25) ? mangle(regex) : regex);
}

const lines = [...cases].map(([regex, verdict]) => '    ' + JSON.stringify([regex, verdict]));
writeFileSync(OUTPUT, '[\n' + lines.join(',\n') + '\n]\n');
