const WebPage = (): JSX.Element => {
  return (
    <div style={{ marginTop: '20px' }}>
      <iframe
        src="https://driouchcity.net/"
        style={{ width: '100%', height: '600px', border: 'none' }}
        title="Webpage Display"
      />
    </div>
  );
};

export default WebPage;
