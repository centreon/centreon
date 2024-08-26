const WebPage = ({ panelOptions }): JSX.Element => {
  const { url } = panelOptions;

  return (
    <div style={{ height: '100%', width: '100%' }}>
      <iframe
        src={url}
        style={{ width: '100%', height: '100%', border: 'none' }}
        title="Webpage Display"
        test-id="Webpage Display"
      />
    </div>
  );
};

export default WebPage;
