module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'scope-enum': [
      2,
      'always',
      [
        'front',
        'back',
        'ci',
        'docker',
        'db',
        'books',
        'agents',
        'deps',
        'worker',
      ],
    ],
  },
};
