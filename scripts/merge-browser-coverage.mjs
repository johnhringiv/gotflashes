/**
 * Merge the per-page coverage snapshots collected by /__coverage__ during a
 * COVERAGE=true browser-test run into a single lcov file for Codecov.
 *
 * Each storage/app/coverage/page-*.json is one page's final
 * window.__coverage__ snapshot (an Istanbul coverage map). Merging sums hit
 * counts; line coverage only cares whether a line was ever hit.
 *
 * Usage: node scripts/merge-browser-coverage.mjs [input-dir] [output-dir]
 * Defaults: storage/app/coverage -> coverage-browser-js/
 */
import { readFileSync, readdirSync, mkdirSync, existsSync } from 'node:fs';
import { join, relative, isAbsolute } from 'node:path';
import libCoverage from 'istanbul-lib-coverage';
import libReport from 'istanbul-lib-report';
import reports from 'istanbul-reports';

const inputDir = process.argv[2] ?? 'storage/app/coverage';
const outDir = process.argv[3] ?? 'coverage-browser-js';

const files = existsSync(inputDir)
    ? readdirSync(inputDir).filter((f) => f.startsWith('page-') && f.endsWith('.json'))
    : [];

if (files.length === 0) {
    console.error(`No page-*.json snapshots in ${inputDir} — did the browser suite run with COVERAGE=true against an instrumented build?`);
    process.exit(1);
}

const map = libCoverage.createCoverageMap({});
let merged = 0;
let skipped = 0;

for (const file of files) {
    try {
        map.merge(JSON.parse(readFileSync(join(inputDir, file), 'utf8')));
        merged++;
    } catch {
        // A snapshot torn by a mid-write page kill is expected tail loss.
        skipped++;
    }
}

// Normalize absolute build-machine paths to repo-relative so Codecov can
// match them to files in the repository.
const normalized = libCoverage.createCoverageMap({});
for (const file of map.files()) {
    const fc = map.fileCoverageFor(file).toJSON();
    const rel = isAbsolute(file) ? relative(process.cwd(), file) : file;
    normalized.addFileCoverage({ ...fc, path: rel });
}

mkdirSync(outDir, { recursive: true });
const context = libReport.createContext({ dir: outDir, coverageMap: normalized });
reports.create('lcovonly', { file: 'lcov.info' }).execute(context);

const summary = normalized.getCoverageSummary();
console.log(
    `Merged ${merged} page snapshot(s) (${skipped} skipped) across ${normalized.files().length} file(s) ` +
    `-> ${outDir}/lcov.info (${summary.lines.pct}% lines)`
);
