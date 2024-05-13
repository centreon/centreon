import { Node } from '../models';

export interface SimpleData {
  id: number;
  name: string;
  status: 'critical' | 'warning' | 'ok';
}

export interface ComplexData {
  count?: number;
  id: number;
  name: string;
  status: 'critical' | 'warning' | 'ok';
}

export const simpleData: Node<SimpleData> = {
  children: [
    {
      children: [
        { data: { id: 2, name: 'A1', status: 'ok' } },
        { data: { id: 3, name: 'A2', status: 'ok' } },
        { data: { id: 4, name: 'A3', status: 'critical' } },
        {
          children: [
            {
              data: { id: 10, name: 'C1', status: 'warning' }
            },
            {
              children: [
                {
                  data: { id: 11, name: 'D1', status: 'ok' }
                },
                {
                  data: { id: 12, name: 'D2', status: 'ok' }
                },
                {
                  data: { id: 13, name: 'D3', status: 'ok' }
                }
              ],
              data: { id: 14, name: 'D', status: 'ok' }
            },
            {
              children: [
                {
                  data: { id: 21, name: 'E1', status: 'critical' }
                }
              ],
              data: { id: 20, name: 'E', status: 'critical' }
            }
          ],
          data: { id: 3, name: 'C', status: 'critical' }
        }
      ],
      data: { id: 6273286320, name: 'A', status: 'critical' }
    },
    { data: { id: 1, name: 'Z', status: 'ok' } },
    {
      children: [{ data: { id: 50, name: 'B1', status: 'warning' } }],
      data: { id: 2, name: 'B', status: 'warning' }
    }
  ],
  data: {
    id: 0,
    name: 'T',
    status: 'critical'
  }
};

export const complexData: Node<ComplexData> = {
  children: [
    {
      children: [
        { data: { id: 2, name: 'Indicator 1', status: 'critical' } },
        {
          children: [
            {
              children: [
                { data: { id: 6, name: 'Indicator 6', status: 'critical' } },
                { data: { id: 7, name: 'Indicator 7', status: 'ok' } }
              ],
              data: { count: 2, id: 0, name: 'critical', status: 'critical' }
            },
            {
              children: [
                {
                  children: [
                    {
                      children: [
                        {
                          data: {
                            id: 8,
                            name: 'Indicator 8',
                            status: 'warning'
                          }
                        }
                      ],
                      data: {
                        count: 1,
                        id: 4,
                        name: 'warning',
                        status: 'warning'
                      }
                    },
                    {
                      children: [
                        {
                          data: {
                            id: 10,
                            name: 'Indicator 10',
                            status: 'ok'
                          }
                        }
                      ],
                      data: {
                        count: 1,
                        id: 0,
                        name: 'ok',
                        status: 'ok'
                      }
                    }
                  ],
                  data: {
                    id: 6,
                    name: 'BA 3',
                    status: 'warning'
                  }
                }
              ],
              data: {
                count: 1,
                id: 4,
                name: 'warning',
                status: 'warning'
              }
            },
            {
              children: [
                { data: { id: 9, name: 'Indicator 9', status: 'ok' } }
              ],
              data: { count: 1, id: 0, name: 'ok', status: 'ok' }
            }
          ],
          data: { id: 3, name: 'BA 2', status: 'critical' }
        }
      ],
      data: { count: 2, id: 6, name: 'critical', status: 'critical' }
    },
    {
      children: [
        { data: { id: 4, name: 'Indicator 4', status: 'warning' } },
        { data: { id: 5, name: 'Indicator 5', status: 'warning' } }
      ],
      data: { count: 2, id: 4, name: 'warning', status: 'warning' }
    },
    {
      children: [
        { data: { id: 6, name: 'Indicator 2', status: 'ok' } },
        {
          children: [
            {
              children: [
                { data: { id: 6, name: 'Indicator 2', status: 'ok' } }
              ],
              data: {
                count: 1,
                name: 'ok',
                status: 'ok'
              }
            }
          ],
          data: { id: 0, name: 'BA 4', status: 'ok' }
        }
      ],
      data: { count: 2, id: 0, name: 'ok', status: 'ok' }
    }
  ],
  data: {
    id: 1,
    name: 'BA 1',
    status: 'critical'
  }
};

export const moreComplexData: Node<ComplexData> = {
  children: [
    {
      children: [
        { data: { id: 2, name: 'Indicator 1', status: 'critical' } },
        {
          children: [
            {
              children: [
                { data: { id: 6, name: 'Indicator 6', status: 'critical' } },
                { data: { id: 7, name: 'Indicator 7', status: 'ok' } }
              ],
              data: { count: 2, id: 0, name: 'critical', status: 'critical' }
            },
            {
              children: [
                {
                  children: [
                    {
                      children: [
                        {
                          data: {
                            id: 8,
                            name: 'Indicator 8',
                            status: 'warning'
                          }
                        }
                      ],
                      data: {
                        count: 1,
                        id: 4,
                        name: 'warning',
                        status: 'warning'
                      }
                    },
                    {
                      children: [
                        {
                          data: {
                            id: 10,
                            name: 'Indicator 10',
                            status: 'ok'
                          }
                        }
                      ],
                      data: {
                        count: 1,
                        id: 0,
                        name: 'ok',
                        status: 'ok'
                      }
                    }
                  ],
                  data: {
                    id: 6,
                    name: 'BA 3',
                    status: 'warning'
                  }
                }
              ],
              data: {
                count: 1,
                id: 4,
                name: 'warning',
                status: 'warning'
              }
            },
            {
              children: [
                { data: { id: 9, name: 'Indicator 9', status: 'ok' } }
              ],
              data: { count: 1, id: 0, name: 'ok', status: 'ok' }
            }
          ],
          data: { id: 3, name: 'BA 2', status: 'critical' }
        }
      ],
      data: { count: 2, id: 6, name: 'critical', status: 'critical' }
    },
    {
      children: [
        { data: { id: 4, name: 'Indicator 4', status: 'warning' } },
        { data: { id: 5, name: 'Indicator 5', status: 'warning' } }
      ],
      data: { count: 2, id: 4, name: 'warning', status: 'warning' }
    },
    {
      children: [
        {
          children: [
            {
              children: [
                { data: { id: 11, name: 'Indicator 11', status: 'ok' } }
              ],
              data: {
                count: 1,
                name: 'ok',
                status: 'ok'
              }
            }
          ],
          data: { id: 0, name: 'BA 3', status: 'ok' }
        },
        { data: { id: 30, name: 'Indicator 30', status: 'ok' } },
        { data: { id: 31, name: 'Indicator 31', status: 'ok' } },
        { data: { id: 32, name: 'Indicator 32', status: 'ok' } },
        { data: { id: 33, name: 'Indicator 33', status: 'ok' } },
        { data: { id: 34, name: 'Indicator 34', status: 'ok' } },
        { data: { id: 35, name: 'Indicator 35', status: 'ok' } },
        { data: { id: 36, name: 'Indicator 36', status: 'ok' } },
        { data: { id: 37, name: 'Indicator 37', status: 'ok' } },
        { data: { id: 38, name: 'Indicator 38', status: 'ok' } }
      ],
      data: { count: 9, id: 0, name: 'ok', status: 'ok' }
    }
  ],
  data: {
    id: 1,
    name: 'BA 1',
    status: 'critical'
  }
};
