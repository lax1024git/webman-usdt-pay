<script setup lang="ts">
import { ElImageViewer } from 'element-plus'
import { computed, ref, PropType } from 'vue'
import { propTypes } from '@/utils/propTypes'

const props = defineProps({
  urlList: {
    type: Array as PropType<string[]>,
    default: (): string[] => []
  },
  zIndex: propTypes.number.def(3000),
  initialIndex: propTypes.number.def(0),
  infinite: propTypes.bool.def(true),
  hideOnClickModal: propTypes.bool.def(true),
  teleported: propTypes.bool.def(true),
  show: propTypes.bool.def(false)
})

const getBindValue = computed(() => {
  const propsData: Recordable = { ...props }
  delete propsData.show
  return propsData
})

const show = ref(props.show)

const close = () => {
  show.value = false
}
</script>

<template>
  <ElImageViewer v-if="show" v-bind="getBindValue" @close="close" />
</template>
