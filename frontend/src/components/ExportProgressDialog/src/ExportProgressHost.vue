<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useExportProgressStore } from '@/store/modules/exportProgress'
import ExportProgressDialog from './ExportProgressDialog.vue'

const progressStore = useExportProgressStore()
const { items } = storeToRefs(progressStore)
</script>

<template>
  <ExportProgressDialog
    v-for="item in items"
    :key="item.jobId"
    :job-id="item.jobId"
    :filename="item.filename"
    :export-type="item.exportType"
  />
</template>
