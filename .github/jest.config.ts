// jest.config.ts
import type { Config } from 'jest';

const config: Config = {
  // ── Runner ────────────────────────────────────────────────────────────────
  preset: 'ts-jest',
  testEnvironment: 'node',

  // ── File discovery ────────────────────────────────────────────────────────
  roots: ['<rootDir>/src', '<rootDir>/tests'],
  testMatch: [
    '**/__tests__/**/*.[jt]s?(x)',
    '**/?(*.)+(spec|test).[jt]s?(x)',
  ],
  testPathIgnorePatterns: ['/node_modules/', '/dist/', '/build/'],

  // ── Module resolution ─────────────────────────────────────────────────────
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
  },

  // ── Transform ─────────────────────────────────────────────────────────────
  transform: {
    '^.+\\.tsx?$': ['ts-jest', { tsconfig: 'tsconfig.json' }],
  },

  // ── Setup ─────────────────────────────────────────────────────────────────
  setupFilesAfterFramework: ['<rootDir>/tests/setup.ts'],

  // ── Coverage ──────────────────────────────────────────────────────────────
  collectCoverage: false,           // off by default; enabled via --coverage flag
  collectCoverageFrom: [
    'src/**/*.{ts,tsx}',
    '!src/**/*.d.ts',
    '!src/**/*.stories.{ts,tsx}',
    '!src/index.ts',
    '!src/types/**',
  ],
  coverageDirectory: 'coverage',
  coverageReporters: [
    'text',
    'text-summary',
    'lcov',          // for Codecov
    'html',          // human-readable HTML report
    'json-summary',  // machine-readable for threshold check
    'cobertura',     // for some CI dashboards
  ],
  coverageThreshold: {
    global: {
      lines: 80,
      branches: 75,
      functions: 80,
      statements: 80,
    },
    // Per-file thresholds (optional):
    // './src/utils/': {
    //   lines: 90,
    //   branches: 85,
    // },
  },

  // ── Reporters ─────────────────────────────────────────────────────────────
  reporters: [
    'default',
    [
      'jest-junit',
      {
        outputDirectory: './test-results',
        outputName: 'junit.xml',
        classNameTemplate: '{classname}',
        titleTemplate: '{title}',
        ancestorSeparator: ' > ',
        usePathForSuiteName: true,
      },
    ],
  ],

  // ── Performance ───────────────────────────────────────────────────────────
  maxWorkers: '50%',
  testTimeout: 30_000,

  // ── Misc ──────────────────────────────────────────────────────────────────
  clearMocks: true,
  restoreMocks: true,
  verbose: true,
};

export default config;
