const Ajv = require("ajv");

/** Test function to show this kludge is working. */
const test200 = () => {
  test('Status code is 200', () => {
    expect(res.getStatus()).to.equal(200);
  });
};

const testJson = () => {
  test('Content-Type header is application/json', () => {
    expect(res.getHeader('content-type')).to.include('application/json;charset=utf-8');
  });

  test('Response body uses json format', () => {
    expect(res.getBody()).to.be.an.instanceof(Object);
  });
};

const notEmpty = () => {
  test('Result is not empty', () => {
    expect(res.getBody().length).above(0);
  });
};

const checkHostListFormat = (columns) => {
  if (typeof columns === 'undefined') {
    columns = [
      'id',
      'name',
      'alias',
      'address',
      'state',
      'state_type',
      'output',
      'max_check_attempts',
      'check_attempt',
      'last_check',
      'last_state_change',
      'last_hard_state_change',
      'acknowledged',
      'instance_name',
      'criticality'
    ];
  }

  const hostListSchema = {
    items: {
      additionalProperties: true,
      properties: {
        alias: { 'type ': 'string' },
        address: { type: 'string' },
        id: { type: 'integer' },
        name: { 'type ': 'string' },
        output: { 'type ': 'string' },
        check_attempt: { type: 'integer' },
        state: { type: 'integer' },
        last_check: { type: ['integer', 'null'] },
        state_type: { 'type ': 'integer' },
        last_hard_state_change: { type: ['integer', 'null'] },
        max_check_attempts: { type: 'integer' },
        acknowledged: { 'type ': 'integer' },
        criticality: { type: ['string', 'null'] },
        last_state_change: { type: ['integer', 'null'] },
        instance_name: { 'type ': 'string' }
      },
      required: columns,
      type: 'object'
    },
    type: 'array'
  };

  test('host list is well formatted', () => {
    const ajv = new Ajv({ strict: false });
    const validate = ajv.compile(hostListSchema);
    validate(res.getBody());
  });
};

const checkServiceListFormat = (columns) => {
  if (typeof columns === 'undefined') {
    columns = [
      'host_id',
      'name',
      'description',
      'service_id',
      'state',
      'state_type',
      'output',
      'perfdata',
      'max_check_attempts',
      'check_attempt',
      'last_check',
      'last_state_change',
      'last_hard_state_change',
      'acknowledged',
      'criticality'
    ];
  }

  const serviceListSchema = {
    items: {
      additionalProperties: true,
      properties: {
        description: { 'type ': 'string' },
        host_id: { type: 'integer' },
        name: { 'type ': 'string' },
        output: { 'type ': 'string' },
        service_id: { type: 'integer' },
        max_check_attempts: { type: 'integer' },
        state: { type: 'integer' },
        check_attempt: { type: 'integer' },
        state_type: { 'type ': 'integer' },
        last_check: { type: ['integer', 'null'] },
        perfdata: { 'type ': 'string' },
        acknowledged: { 'type ': 'integer' },
        last_hard_state_change: { type: ['integer', 'null'] },
        criticality: { type: ['string', 'null'] },
        last_state_change: { type: ['integer', 'null'] }
      },
      required: columns,
      type: 'object'
    },
    type: 'array'
  };

  test('service list is well formatted', () => {
    const ajv = new Ajv({ strict: false });
    const validate = ajv.compile(serviceListSchema);
    validate(res.getBody());
  });
};

const checkHostList = (columns) => {
  test200();
  testJson();
  notEmpty();
  checkHostListFormat(columns);
};

const checkServiceList = (columns) => {
  test200();
  testJson();
  notEmpty();
  checkServiceListFormat(columns);
};

module.exports = {
  checkHostList,
  checkServiceList
};
