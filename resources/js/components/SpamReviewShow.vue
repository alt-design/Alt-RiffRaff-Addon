<template>
  <div>
    <h1>Spam Review Form Submission - {{ id }}</h1>

    <div class="mt-4">
      <p>
        This form submission received a score of <span :class="{ 'text-red-500' : score > threshold }" >{{ score }} / {{ threshold }}</span> and may be considered spam based on its content. You can choose to delete it or mark it as a legitimate submission below by choosing to "release" it.
      </p>
    </div>

    <code>
      <div class="text-white bg-black w-full p-4 rounded-md">{{ item }}</div>
    </code>

    <div class="flex h-full">
      <button @click="back" class="btn text-blue-500 mr-2">Back</button>
      <button @click="release(id)" class="btn text-green-500 mr-2">Release</button>
      <button @click="destroy(id)" class="btn text-red-500 mr-2">Delete</button>
    </div>
  </div>
</template>

<script>
export default ({
  props: {
    id: String,
    item: Array,
    score: Number,
    threshold: Number
  },
  methods: {
    back() {
      window.history.back()
    },
    release(id) {
      Statamic.$axios.post(cp_url('alt-design/riffraff/release/' + id)).then(res => {
        window.location.href = cp_url('alt-design/riffraff')
      }).catch(err => {
        // handle error
      })
    },
    destroy(id) {
      if (confirm('Are you sure?')) {
        Statamic.$axios.delete(cp_url('alt-design/riffraff/' + id)).then(res => {
          window.location.href = cp_url('alt-design/riffraff')
        }).catch(err => {
          // handle error
        })
      }
    }
  }
})
</script>
