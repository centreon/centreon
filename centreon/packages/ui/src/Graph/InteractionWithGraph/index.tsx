import ZoomPreview from './ZoomPreview';

interface Props {
  renderAreaToInteractWith: JSX.Element;
  zoomPreviewData: any;
}

const InteractionWithGraph = ({
  zoomPreviewData,
  renderAreaToInteractWith
}: Props): JSX.Element => {
  return (
    <g>
      <ZoomPreview data={zoomPreviewData} />
      {renderAreaToInteractWith}
    </g>
  );
};

export default InteractionWithGraph;
