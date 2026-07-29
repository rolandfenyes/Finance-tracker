module.exports = {
  displayName: 'api-unit',
  rootDir: '../..',
  testEnvironment: 'node',
  testMatch: [
    '<rootDir>/apps/api/src/**/*.spec.ts',
    '<rootDir>/apps/api/test/**/*.e2e-spec.ts',
  ],
  setupFiles: ['<rootDir>/apps/api/test/setup-unit-environment.ts'],
  transform: {
    '^.+\\.ts$': ['ts-jest', { tsconfig: '<rootDir>/apps/api/tsconfig.spec.json' }],
  },
  collectCoverageFrom: [
    '<rootDir>/apps/api/src/**/*.ts',
    '!<rootDir>/apps/api/src/main.ts',
    '!<rootDir>/apps/api/src/openapi/*-openapi.ts',
  ],
  coverageDirectory: '<rootDir>/coverage/api/unit',
};
