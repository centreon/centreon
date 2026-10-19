import TestQueryProvider from '../api/TestQueryProvider';
import Pagination from './Pagination';

export const Default = (): JSX.Element => (
  <div
    style={{
      alignItems: 'center',
      display: 'flex',
      height: '100vh',
      justifyContent: 'center',
      width: '100%'
    }}
  >
    <div
      style={{ boxShadow: '2px 2px 4px rgba(0, 0, 0, 0.2)', height: '176px' }}
    >
      <TestQueryProvider>
        <Pagination
          api={{ baseEndpoint: '/test/listing', queryKey: ['test'] }}
        />
      </TestQueryProvider>
    </div>
  </div>
);
