interface Options {
  // The first provider is enabled when created: AI features need one enabled
  // provider. A later one starts disabled, since enabling it disables the
  // current one.
  isActivated: boolean;
}

export const getDefaultValues = ({ isActivated }: Options) => ({
  apiToken: '',
  currency: null,
  defaultModel: '',
  description: null,
  endpointUrl: '',
  fastModel: null,
  isActivated,
  limitDaily: null,
  limitMonthly: null,
  name: '',
  priceDefaultInput: null,
  priceDefaultOutput: null,
  priceFastInput: null,
  priceFastOutput: null,
  type: null
});
