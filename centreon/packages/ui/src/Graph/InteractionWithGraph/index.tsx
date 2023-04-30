import ZoomPreview from './ZoomPreview';

const InteractionWithGraph = ({
  zoomPreviewData,
  renderAreaToInteractWith
}: any): JSX.Element => {
  return (
    <g>
      <ZoomPreview data={zoomPreviewData} />
      {renderAreaToInteractWith}
    </g>
  );
};

export default InteractionWithGraph;
