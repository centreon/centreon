import { TreeNode } from '../ResourceTree.types';

export const rawTree: TreeNode = {
  children: [
    {
      children: [
        {id: 'A1', name: 'A1', status: 'ok', group: 'other'},
        {id: 'A2', name: 'A2', status: 'neutral', group: 'storage'},
        {id: 'A3', name: 'A3', status: 'ok', group: 'server'},
        {
          children: [
            {
              id: 'C1',
              name: 'C1',
              status: 'ok',
              group: 'storage'
            },
            {
              children: [
                {
                  id: 'D1',
                  name: 'D1',
                  status: 'ok',
                  group: 'server'
                }, {
                  id: 'D2',
                  name: 'D2',
                  status: 'ok',
                  group: 'server'
                }, {
                  id: 'D3',
                  name: 'D3',
                  status: 'ok',
                  group: 'server'
                }
              ],
              id: 'D',
              name: 'D',
              status: 'ok',
              group: 'router'
            }
          ],
          id: 'C',
          name: 'C',
          status: 'warn',
          group: 'firewall'
        }
      ],
      id: 'A',
      name: 'A',
      status: 'ok',
      group: 'router'
    },
    {id: 'Z', name: 'Z', status: 'error', group: 'other'},
    {
      children: [
        {
          id: 'B1',
          name: 'B1',
          status: 'ok',
          group: 'server'
        }, {
          id: 'B2',
          name: 'B2',
          status: 'ok',
          group: 'server'
        }, {
          id: 'B3',
          name: 'B3',
          status: 'error',
          group: 'server'
        }
      ],
      id: 'B',
      name: 'B',
      status: 'ok',
      group: 'cloud'
    }
  ],
  id: 'T',
  name: 'T',
  status: 'neutral',
  group: 'cloud'
};
