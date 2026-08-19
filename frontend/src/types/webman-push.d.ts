declare module '@/utils/webman-push.js' {
  export class Push {
    constructor(options: { url: string; app_key: string; auth?: string; heartbeat?: number })
    subscribe(channel: string): {
      on: (event: string, cb: (data: any) => void) => void
      subscribed?: boolean
    }
    connection?: { close?: () => void; state?: string }
  }
}
