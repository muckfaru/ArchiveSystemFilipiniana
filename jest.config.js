module.exports = {
  testEnvironment: 'jsdom',
  testMatch: ['**/*.test.js'],
  collectCoverageFrom: [
    'assets/js/**/*.js',
    '!assets/js/**/*.test.js'
  ],
  transform: {
    '^.+\\.js$': 'babel-jest'
  }
};
