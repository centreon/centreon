import { Node } from '../models';

export interface SimpleData {
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
