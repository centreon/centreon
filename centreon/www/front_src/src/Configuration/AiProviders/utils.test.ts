import { isSameEndpoint } from './utils';

describe('isSameEndpoint', () => {
  it('matches identical endpoints', () => {
    expect(
      isSameEndpoint(
        'https://llm-gateway.example.com',
        'https://llm-gateway.example.com'
      )
    ).toBe(true);
  });

  it('ignores trailing slashes', () => {
    expect(
      isSameEndpoint(
        'https://llm-gateway.example.com/',
        'https://llm-gateway.example.com'
      )
    ).toBe(true);
    expect(
      isSameEndpoint(
        'https://llm-gateway.example.com/v1',
        'https://llm-gateway.example.com/v1//'
      )
    ).toBe(true);
  });

  it('tells different endpoints apart', () => {
    expect(
      isSameEndpoint(
        'https://other-gateway.example.com',
        'https://llm-gateway.example.com'
      )
    ).toBe(false);
    expect(
      isSameEndpoint(
        'https://llm-gateway.example.com/v2',
        'https://llm-gateway.example.com/v1'
      )
    ).toBe(false);
  });

  it('treats a missing endpoint as an empty one', () => {
    expect(isSameEndpoint(null, undefined)).toBe(true);
    expect(isSameEndpoint('', null)).toBe(true);
    expect(isSameEndpoint(undefined, '/')).toBe(true);
    expect(isSameEndpoint('https://llm-gateway.example.com', null)).toBe(false);
  });
});
