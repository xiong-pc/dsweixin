import { watch, onBeforeUnmount } from 'vue';
import { useAppStore } from '@/store/app';

export function useWatermark() {
  const appStore = useAppStore();
  let watermarkDiv: HTMLDivElement | null = null;

  function createWatermark(text: string) {
    removeWatermark();
    const canvas = document.createElement('canvas');
    canvas.width = 300;
    canvas.height = 200;
    const ctx = canvas.getContext('2d')!;
    ctx.rotate((-20 * Math.PI) / 180);
    ctx.font = '16px Arial';
    ctx.fillStyle = 'rgba(180, 180, 180, 0.3)';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, canvas.width / 2, canvas.height / 2);

    watermarkDiv = document.createElement('div');
    watermarkDiv.id = 'watermark';
    watermarkDiv.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 9999;
      pointer-events: none;
      background-repeat: repeat;
      background-image: url(${canvas.toDataURL('image/png')});
    `;
    document.body.appendChild(watermarkDiv);
  }

  function removeWatermark() {
    if (watermarkDiv) {
      watermarkDiv.remove();
      watermarkDiv = null;
    }
  }

  watch(
    [() => appStore.watermarkEnabled, () => appStore.watermarkText],
    ([enabled, text]) => {
      if (enabled && text) {
        createWatermark(text);
      } else {
        removeWatermark();
      }
    },
    { immediate: true }
  );

  onBeforeUnmount(() => {
    removeWatermark();
  });
}
