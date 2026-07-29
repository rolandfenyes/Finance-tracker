module.exports = {
  displayName: 'api-integration',
  rootDir: '../..',
  testEnvironment: 'node',
  testMatch: ['<rootDir>/apps/api/test/**/*.integration-spec.ts'],
  transform: {
    '^.+\\.ts$': ['ts-jest', { tsconfig: '<rootDir>/apps/api/tsconfig.spec.json' }],
  },
  coverageDirectory: '<rootDir>/coverage/api/integration',
};
