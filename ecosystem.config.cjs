module.exports = {
  apps: [
    {
      name: 'taxsaathi-mern',
      script: './server/index.mjs',
      cwd: __dirname,
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      watch: false,
      max_memory_restart: '512M',
      env: {
        NODE_ENV: 'production',
        HOST: '0.0.0.0',
        PORT: 4001,
        PUBLIC_URL: 'http://103.160.145.116:4001',
        MONGODB_URI: process.env.MONGODB_URI || 'mongodb://127.0.0.1:27017/taxsaathi2'
      }
    }
  ]
};
