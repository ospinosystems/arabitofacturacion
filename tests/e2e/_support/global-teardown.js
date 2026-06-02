// @ts-check
const { stopPinpadMock } = require('./mocks/pinpad-mock');

module.exports = async function globalTeardown() {
    await stopPinpadMock();
};
