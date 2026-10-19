import Zoom from './Zoom';

const circle = (
  x: number,
  y: number,
  fill: string,
  r: number,
  stroke?: string
): JSX.Element => (
  <g key={`${x}-${y}`} style={{ transform: `translate(${x}px, ${y}px)` }}>
    <circle fill={fill} r={r} stroke={stroke} />
  </g>
);

const contents = {
  negative: () => (
    <g>
      {[
        circle(-300, -150, 'blue', 50, 'black'),
        circle(600, 500, 'green', 70),
        circle(150, 600, 'red', 70)
      ]}
    </g>
  ),
  several: () => (
    <g>
      {[
        circle(300, 150, 'blue', 50, 'black'),
        circle(600, 500, 'green', 70),
        circle(150, 600, 'red', 70)
      ]}
    </g>
  ),
  single: () => circle(300, 150, 'blue', 50, 'black')
};

interface Props {
  content?: keyof typeof contents;
  minimapPosition?: 'bottom-right' | 'top-left' | 'top-right' | 'bottom-left';
  showMinimap: boolean;
}

export const Default = ({
  content = 'single',
  minimapPosition,
  showMinimap
}: Props): JSX.Element => (
  <div style={{ height: '400px', width: '100%' }}>
    <Zoom minimapPosition={minimapPosition as never} showMinimap={showMinimap}>
      {contents[content]}
    </Zoom>
  </div>
);
