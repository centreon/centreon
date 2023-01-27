const mockedAnyLogger = {
  error: jest.fn(),
  warn: jest.fn()
};

const anyLogger = () => mockedAnyLogger;

export default anyLogger;
